<?php

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();
arch()->preset()->relaxed();

// block usage of hasPermissionTo in App and Tests because `can` must be used to ensure `Gate::before` is called.
arch()->expect('App')->not->toUse('hasPermissionTo ');
arch()->expect('Tests')->not->toUse('hasPermissionTo ');