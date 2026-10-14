<?php

namespace Streams\Sdk\Console\Make;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Streams\Sdk\Attributes\Generated;
use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Support\AppTree;

/** Plans all edits before writing; restores original bytes on a write failure. */
class RenameStage
{
    public function apply(TreeEditor $treeEditor, AppTree $tree, string $treeFile, string $output, string $from, string $to, bool $dryRun): array
    {
        if (! $tree->has($from) || $tree->has($to)) {
            throw new TreeEditException('Rename source must exist and destination must be absent.', 'path');
        }

        $treeEdit = $treeEditor->rename($treeFile, $from, $to);
        $lock = StreamLock::read($output);
        $files = $lock->entries[$from] ?? [];

        if ($files === [] || isset($lock->entries[$to])) {
            throw new TreeEditException('Rename requires an existing source lock entry and no destination lock entry.');
        }

        $writes = [$treeEdit['file'] => $treeEdit['contents']];
        $renamedFiles = [];
        foreach ($lock->entries as $nodePath => $nodeFiles) {
            if ($nodePath !== $from && ! str_starts_with($nodePath, $from.'.')) {
                continue;
            }
            $destination = $to.substr($nodePath, strlen($from));
            if (isset($lock->entries[$destination])) {
                throw new TreeEditException('Destination lock entry already exists.');
            }
            foreach ($nodeFiles as $relative => $hash) {
                if (! StreamLock::validFile($relative)) {
                    throw new TreeEditException('Invalid file path in streams.lock.');
                }
                $absolute = rtrim($output, '/').'/'.$relative;
                $source = is_file($absolute) ? (string) file_get_contents($absolute) : '';
                if (StreamLock::hashContents($source) !== $hash || Generated::pathFromSource($source) !== $nodePath) {
                    throw new TreeEditException("Cannot rename [{$relative}]: missing tag or hand edit; hash does not match streams.lock.");
                }

                $editor = PhpEditor::fromString($source, $relative);
                $traverser = new NodeTraverser;
                $traverser->addVisitor(new class($nodePath, $destination) extends NodeVisitorAbstract
                {
                    public function __construct(private string $from, private string $to) {}

                    public function enterNode(Node $node)
                    {
                        if ($node instanceof Attribute && $node->name->toString() === 'Generated') {
                            foreach ($node->args as $arg) {
                                if ($arg->value instanceof String_ && $arg->value->value === $this->from) {
                                    $arg->value->value = $this->to;
                                }
                            }
                        }
                        if ($node instanceof ClassMethod && $node->name->toString() === 'nodePath') {
                            foreach ($node->stmts ?? [] as $stmt) {
                                if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof String_ && $stmt->expr->value === $this->from) {
                                    $stmt->expr->value = $this->to;
                                }
                            }
                        }
                        return null;
                    }
                });
                $editor->withStatements($traverser->traverse($editor->statements()));
                $contents = str_replace(' * Node: '.$nodePath."\n", ' * Node: '.$destination."\n", $editor->print());
                $writes[$absolute] = $contents;
                $lock->put($destination, $relative, StreamLock::hashContents($contents));
                $renamedFiles[] = $relative;
            }
            unset($lock->entries[$nodePath]);
        }
        $writes[StreamLock::path($output)] = $lock->encode();

        if (! $dryRun) {
            $originals = [];
            try {
                foreach ($writes as $file => $contents) {
                    $originals[$file] = (string) file_get_contents($file);
                    if (file_put_contents($file, $contents) === false) {
                        throw new TreeEditException("Could not write [{$file}].");
                    }
                }
            } catch (\Throwable $e) {
                foreach ($originals as $file => $contents) {
                    file_put_contents($file, $contents);
                }
                throw $e;
            }
        }

        return ['from' => $from, 'to' => $to, 'files' => $renamedFiles, 'written' => ! $dryRun];
    }
}
