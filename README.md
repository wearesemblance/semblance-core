# Semblance Application Core

A WordPress framework for rapidly developing plugins/themes.

Not for production... this is more a concept than anything.

Could use some suggestions and PRs.


## Quick Start

```
<?php

namespace WAS\TestApp;

// Register the app
add_action( 'was_core/register_apps', __NAMESPACE__ . '\register' );
function register() {
  $app = \WAS\register_app( 'test_app' );
}

```
