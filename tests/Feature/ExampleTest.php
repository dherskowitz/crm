<?php

test('the root path redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});
