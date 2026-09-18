<?php

use App\Services\Supplier\Import\ImportLog;

beforeEach(function () {
    $this->runId = 900_000 + random_int(1, 99_999);
    $this->path = storage_path("logs/imports/{$this->runId}.log");
});

afterEach(function () {
    if (is_file($this->path)) {
        unlink($this->path);
    }
});

it('keeps the first messages for the run page and writes all of them to the file', function () {
    $log = new ImportLog($this->runId, 2);

    $log->add('первая');
    $log->add('вторая');
    $log->add('третья');
    $log->close();

    expect($log->messages())->toBe(['первая', 'вторая'])
        ->and($log->relativePath())->toBe("logs/imports/{$this->runId}.log")
        ->and(file_get_contents($this->path))->toContain('третья');
});

it('writes a repeated problem only once', function () {
    $log = new ImportLog($this->runId, 10);

    $log->addOnce('category:x', 'Категории «X» нет');
    $log->addOnce('category:x', 'Категории «X» нет');
    $log->close();

    expect($log->messages())->toBe(['Категории «X» нет']);
});

it('leaves no file when there was nothing to report', function () {
    $log = new ImportLog($this->runId, 10);
    $log->close();

    expect($log->relativePath())->toBeNull()
        ->and(is_file($this->path))->toBeFalse();
});

it('replaces an old file with the same run number', function () {
    file_put_contents($this->path, "старый прогон\n");

    $log = new ImportLog($this->runId, 10);
    $log->add('новый прогон');
    $log->close();

    expect(file_get_contents($this->path))->not->toContain('старый прогон')
        ->and(file_get_contents($this->path))->toContain('новый прогон');
});
