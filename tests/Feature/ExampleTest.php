<?php

it('la raíz redirige al login para visitantes', function () {
    $this->get('/')->assertRedirect(route('login'));
});
