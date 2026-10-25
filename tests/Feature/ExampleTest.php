<?php

it('renders the scaffold shell', function () {
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee('id="app"', false);
});
