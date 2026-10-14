# Golden-file tests

Use `GoldenFiles::assertGenerated($generate, $goldenDirectory)` in a PHPUnit test.
The callable receives a fresh, empty temporary output directory. Invoke your
generator or Artisan command with that directory as its output target and assert
its exit status inside the callable. The helper compares the entire recursive file
set, including hidden and empty files, byte-for-byte against the golden directory.
Missing and unexpected files fail too; content changes report a unified diff with
`golden/` and `actual/` filenames. Empty directories are not part of the comparison.
Symbolic links are rejected. Temporary output is removed after success, assertion
failure, or a generator exception. The helper never rewrites golden expectations.

```php
GoldenFiles::assertGenerated(function (string $output): void {
    $this->artisan('your:generator', ['--output' => $output])->assertExitCode(0);
}, __DIR__.'/../Fixtures/Golden/your-generator-v1');
```

Keep reviewed golden expectations under `tests/Fixtures/Golden`. GroupVitals
reference snapshots under `tests/Fixtures/References` are source examples, not
automatically generated expectations. The helper's own self-contained tests use
`helper-v1` and require no GroupVitals checkout, database, or Streams UI runtime.
