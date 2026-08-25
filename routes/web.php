<?php

use Illuminate\Support\Facades\Route;

// This app has no public-facing marketing page -- the Filament panel is the
// entire product. Redirecting (rather than deleting the route) keeps a
// bookmark to "/" useful instead of 404ing. Also means this app never needs
// a Vite/npm build step in production -- the default welcome.blade.php
// (which @vite()s assets) was the only thing that ever required one.
Route::get('/', fn () => redirect('/admin'));
