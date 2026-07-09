<?php

test('api status endpoint rejects requests without an api key', function () {
    $this->getJson('/api/status')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('api status endpoint rejects requests with an invalid api key', function () {
    $this->withHeader('X-Api-Key', 'wrong-key')
        ->getJson('/api/status')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('api status endpoint returns service status with a valid api key', function () {
    $this->withHeader('X-Api-Key', 'test-api-key')
        ->getJson('/api/status')
        ->assertSuccessful()
        ->assertJson([
            'status' => 'ok',
            'service' => config('app.name'),
        ]);
});
