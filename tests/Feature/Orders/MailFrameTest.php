<?php

use App\Mail\ImportFailedMail;
use App\Mail\PasswordResetMail;
use App\Models\ImportRun;

it('frames every letter with the shop name from the settings and the site address', function () {
    setting('site.name', 'Гастроснаб');

    $html = (new PasswordResetMail('Ирина', 'https://example.test/reset', 60))->render();

    expect($html)->toContain('>Гастроснаб</p>')
        ->and($html)->toContain('href="'.url('/').'"')
        ->and($html)->toContain('#0072b0');
});

it('sends the import failure in the same frame and palette', function () {
    setting('site.name', 'Гастроснаб');
    $run = ImportRun::factory()->create();

    $html = (new ImportFailedMail($run, 'Файл обрезан'))->render();

    expect($html)->toContain('>Гастроснаб</p>')
        ->and($html)->toContain('Файл обрезан')
        ->and($html)->not->toContain('#0b5fff')
        ->and($html)->not->toContain('#b42318');
});
