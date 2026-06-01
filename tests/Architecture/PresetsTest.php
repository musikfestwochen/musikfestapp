<?php

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();

arch()->expect('App')
    ->classes()->not->toBeFinal()
    ->classes()->not->toHavePrivateMethods()
    ->toUseStrictTypes();
