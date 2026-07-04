<?php

it('responde en la raíz', function () {
    $this->get('/')->assertStatus(200);
});
