<?php

namespace Streams\Sdk\Console\Onboard;

use Illuminate\Support\Str;
use Streams\Sdk\Console\Make\BuildStage;
use Streams\Sdk\Console\Make\Kinds\BuiltInKinds;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Console\Make\Paths;
use Streams\Sdk\Console\Make\TreeEditor;
use Streams\Sdk\Support\AppTree;

/**
 * Emits the built-in manage chain (page + table + modal add) under a stream
 * handle, then compiles Panel PHP via BuildStage.
 *
 * Does not Composer-require streams/ui — that remains a separate pending step.
 */
final class ManageUiEmitter
{
    public function __construct(
        protected ?KindRegistry $registry = null,
        protected ?Paths $paths = null,
    ) {
        $this->registry ??= KindRegistry::withBuiltIns();
        $this->paths ??= Paths::fromConfig();
    }

    /**
     * @return array{
     *     handle: string,
     *     tree_files: list<string>,
     *     php_files: list<string>,
     *     steps: list<array{kind: string, path: string}>
     * }
     */
    public function emit(string $domain, string $basePath): array
    {
        $domain = Str::studly($domain);
        $handle = Str::snake($domain);
        $base = rtrim($basePath, '/');
        $root = $base.'/streams.json';

        if (! is_file($root)) {
            throw new \RuntimeException("App tree missing at [{$root}]; emit the domain scaffold first.");
        }

        $entity = Str::studly(Str::singular($domain));
        $titleField = $handle === 'todos' ? 'title' : 'name';

        $steps = BuiltInKinds::manage()->expandUnder($handle, [
            'title' => 'Manage '.$domain,
            'section' => $handle,
            'add_label' => 'Add '.$entity,
            'table_name' => $domain,
            'form' => [
                'handle' => 'form',
                'name' => 'Add '.$entity,
                'sections' => [
                    'basics' => [
                        'handle' => 'basics',
                        'name' => 'Basics',
                        'fields' => [
                            [
                                'handle' => $titleField,
                                'type' => 'string',
                                'required' => true,
                            ],
                        ],
                    ],
                ],
                'buttons' => [
                    ['handle' => 'save', 'label' => 'Save'],
                    ['handle' => 'cancel', 'label' => 'Cancel'],
                ],
            ],
        ]);

        $editor = TreeEditor::forRegistry($this->registry);
        $treeFiles = [];

        foreach ($steps as $step) {
            $kind = $this->registry->get($step['kind']);
            $errors = $kind->validate($step['input']);
            if ($errors !== []) {
                $messages = array_map(fn (array $e) => $e['field'].': '.$e['message'], $errors);
                throw new \RuntimeException('Invalid '.$step['kind'].' at '.$step['path'].': '.implode('; ', $messages));
            }

            $result = $editor->apply($root, $step['path'], $step['input'], false);
            $treeFiles[] = $base.'/'.$result['file'];
        }

        $tree = AppTree::load($root);
        $build = new BuildStage($this->paths);
        $plan = $build->plan($tree, $handle, $base);
        $build->apply($plan, $base);

        return [
            'handle' => $handle,
            'tree_files' => array_values(array_unique($treeFiles)),
            'php_files' => array_values(array_unique(array_merge($plan->created, $plan->modified))),
            'steps' => array_map(fn (array $step) => [
                'kind' => $step['kind'],
                'path' => $step['path'],
            ], $steps),
        ];
    }
}
