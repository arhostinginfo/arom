<?php

// Front controller for the folder address (http://localhost/nursery-app, no /public); see .htaccess next to this file.
// Running from here makes Laravel build every link, redirect and asset URL without /public.
require __DIR__.'/public/index.php';
