<?php
/**
 * Login View - delegates rendering to the standalone auth layout.
 * Variables passed by AuthController: $error (optional)
 *
 * The actual HTML/CSS/JS is inside layouts/auth.php which is now
 * a fully self-contained page (no section/extend system).
 */
echo view('layouts/auth', $this->data ?? []);
