# Joole Component: Router

This component allows you to configure routes for your project.
## Getting started

* Install this dependency via composer: <code>composer install zloynick/joole-components-routing</code>

## Configuration

Add to components this in your joole.php configuration file:
<pre>
<code>
'components' => [
        ...,
        [
            'name' => 'router',
            'class' => \joole\components\routing\RoutingComponent::class,
            // Component options
            'options' => [
                // Routes path
                'routes' => __DIR__.'/routes/',
            ],
        ],
        ...,
    ],
</code>
</pre>

## Using

Create your routes in the folder that you specified in the component configuration:

<pre>
----------- routes.php -----------
<code>
...
// Closure using.
BaseRouter::register('route.test', '/my/action/:name/:and_id', function(string $name, int $and_id):\joole\framework\http\Response{
          return response();
      }
);
// Controller using.
BaseRouter::register('route.test', '/my/action/:name/:and_id', ['\app\controllers\DefaultController::class', 'index']);
// OR
BaseRouter::register('route.test', '/my/action/:name/:and_id', '\app\controllers\DefaultController::class@index');
...
</code>
</pre>

## Validation of requests

Use [Validator](https://github.com/ZloyNick/joole-framework/blob/master/src/validator/http/RequestValidator.php) to create a request parameter validation class.

Example:

<pre>
<code>
...
BaseRouter::register('route.test', '/my/action/:name/:and_id', function(string $name, int $and_id):\joole\framework\http\Response{
          return response();
      }
)->withValidators(YourValidator1::class, ..., new Validator2());
...
</code>
</pre>