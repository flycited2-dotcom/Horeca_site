<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/client', fn () => ['ip' => request()->ip(), 'secure' => request()->isSecure()]);
});

it('takes the buyer address and HTTPS from the proxies of the server', function () {
    // The nginx of the store container reaches PHP from the Docker network.
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.3'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'])
        ->getJson('/_test/client')
        ->assertExactJson(['ip' => '203.0.113.7', 'secure' => true]);
});

it('ignores forwarded headers from anyone outside the server', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'])
        ->getJson('/_test/client')
        ->assertExactJson(['ip' => '198.51.100.20', 'secure' => false]);
});
