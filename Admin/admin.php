<?php

// This file is the admin sidebar partial - a view, not a guard. It previously
// carried its own access check with a redirect and no exit(), which failed open
// exactly like auth.php and checkAdmin.php did. Authorisation now lives in
// checkAdmin.php, which every protected page requires as its first statement,
// so duplicating a weaker check here served no purpose.
