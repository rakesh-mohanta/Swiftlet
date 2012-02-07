Swiftlet
========

Swiftlet is quite possibly the smallest 
[MVC](http://en.wikipedia.org/wiki/Model-view-controller) framework you'll ever 
use. And it's swift.

*Licensed under the [MIT license](http://www.opensource.org/licenses/mit-license.php).*


Buzzword compliance
-------------------

✔ Micro-Framework  
✔ Namespaced  
✔ Unit tested  
✔ Pluggable  
✔ PHP5  
✔ MVC  
✔ OOP  

✘ ORM  


Installation in three easy steps
--------------------------------

* Step 1: Clone (or download and extract) Swiftlet into a directory on your PHP
  supported web server.
* Step 2: Congratulations, Swiftlet is now up and running.
* Step 3: There is no step 3.


Getting started: controllers and views
--------------------------------------

Let's create a page. Each page consists of a controller and at least one view.

Controllers house the 
[business logic](http://en.wikipedia.org/wiki/Business_logic) of the page while 
views should be limited to simple UI logic (loops and switches).

**Controller `controllers/Foo.php`**

```php
<?php
namespace Swiftlet\Controllers;

use Swiftlet\App, Swiftlet\View;

class Foo extends \Swiftlet\Controller
{
	protected $_title = 'Foo'; // Optional but recommended

	public function index()
	{
		// Pass a variable to the view
		View::set('hello world', 'Hello world!');
	}
}
```

Important: class names are written in 
[CamelCase](http://en.wikipedia.org/wiki/CamelCase) and match their filename.


**View `views/foo.html.php`**

```php
<?php namespace Swiftlet ?>

<h1><?php echo View::getTitle() ?></h1>

<p>
	<?php echo View::get('hello world') ?>
</p>
```

Variables can be passed from controller to view using `View::set()` and 
`View::get()`. By default values are automatically made safe for use in HTML.

You can now view the page by navigating to `http://<swiftlet>/foo` in your web
browser!


Routing
-------

Notice how we can access the page at `/foo` by simply creating a controller 
named `Foo`. The application (Swiftlet) maps URLs to controllers, actions and
arguments.

Consider this URL: `/foo/bar/baz/qux`

In this case `foo` defines the controller and view, `bar` the action and `baz` 
and `qux` are arguments. If the controller or action is missing from the URL 
they will default to `index` (`/` will call `index()` on `Index`).

Underscores in the controller name are translated to directory separators, so
`/foo_bar` will point to `controllers/Foo/Bar.php`.


Actions and arguments
---------------------

Actions are methods of the controller. A common example might be `edit` or
`delete`:

`/blog/edit/1`

This will call the function `edit()` on `Blog` with `1` as the argument (the 
id of the blog post to edit).

If the action doesn't exist `notImplemented()` will be called instead.  This 
will throw an exception by default but can be overridden.

The action name and arguments can be accessed through `App::getAction()` and 
`App::getArgs()` respectively.

Note: if you want to use different HTML files for each action you can change 
the view with `App::setView($viewName)`.


Models
------

Let's throw a model into the mix and update the controller.

**Model `models/Foo.php`**

```php
<?php
namespace Swiftlet\Models;

class Foo extends \Swiftlet\Model
{
	public function getHelloWorld()
	{
		return 'Hello world!';
	}
}
```

**Controller `controllers/Foo.php`**

```php
<?php
namespace Swiftlet\Controllers;

use Swiftlet\App, Swiftlet\View;

class Foo extends \Swiftlet\Controller
{
	protected $_title = 'Foo';

	public function index()
	{
		// Get an instance of the Example class (models/Example.php)
		$exampleModel = App::getModel('example');

		$helloWorld = $exampleModel->getHelloWorld();

		View::set('hello world', $helloWorld);
	}
}
```

Controllers get their data from models. Code for querying a database,
reading/writing files and parsing data all belongs in a model. You can create as
many models as you like; they aren't tied to specific controllers.

A model can instantiated using `App::getModel($modelName)`. To allow re-use, use 
`App::getSingleton($modelName)` instead as this will only create a single 
instance when called multiple times.


Plugins and hooks
-----------------

Plugins implement [hooks](http://en.wikipedia.org/wiki/Hooking). Hooks are entry
points for code that extends the application. Swiftlet has a few core hooks but 
they can be registered pretty much anywhere using
`App::registerHook($hookName)`.  

**Plugin `plugins/Foo.php`**

```php
<?php
namespace Swiftlet\Plugins;

use Swiftlet\App, Swiftlet\View;

class Foo extends \Swiftlet\Plugin
{
	public function actionAfter()
	{
		// Overwrite our previously set "hello world" variable
		if ( get_class(App::getController()) === 'Swiftlet\Controllers\Foo' ) {
			View::set('hello world', 'Hi world!');
		}
	}
}
```

This plugin implements the core `actionAfter` hook and changes the view 
variable `hello world` from our previous example to `Hi world!`.

Plugins don't need to be installed or activated, all files in the `/plugins`
directory are automatically included and their classes instantiated. They
are hooked in alphabetical order. There is currently no dependency support.

The core hooks are:

* `actionBefore`  
Called before each action

* `actionAfter` 
Called after each action


Configuration
-------------

No configuration is needed to run Swiftlet. If you're writing a model that
does require configuration, e.g. credentials to establish a database connection,
you can use the Config class:

```php
<?php
Config::set('variable', 'value');

$value = Config::get('variable');
```

Values can be set in `config.php` or a custom file.


--------------------------------------------------------------------------------


Public abstract methods
-----------------------

All applicaion and view methods can be called statically, e.g.
`App::getAction()` and `View::getTitle()`.


**Application `Swiftlet\App`**

* `string getAction()`  
Name of the action

* `array getArgs()`  
List of arguments passed in the URL

* `object getModel(string $modelName)`  
Create a new model instance

* `object getSingleton(string $modelName)`  
Create or return an existing model instance

* `string getView()`  
Name of the view

* `setView(string $view)`  
Change the view (use a different HTML file)

* `object getController()`  
Reference to the controller object 

* `array getPlugins()`  
All plugin instances

* `array getHooks()`  
All registered hooks

* `string getRootPath()`  
Absolute client-side path to website root

* `registerHook(string $hookName, array $params)`  
Register a hook


**View `Swiftlet\View`**

* `string getTitle()`  
Title of the page

* `mixed get(string $variable [, bool $htmlEncode = true ])`  
Get a view variable, encoded for safe use in HTML by default

* `set(string $variable [, mixed $value ])`  
Set a view variable

* `htmlEncode(mixed $value)` 
Recursively make a value safe for HTML

* `htmlDecode(mixed $value)` 
Recursively decode a previously encoded value to be rendered as HTML


**Controller `Swiftlet\Controller`**

* `string getTitle()`  
Title of the page

* `index()`  
Default action

* `notImplemented()`  
Fallback action if action doesn't exist


**Config `Swiftlet\Config`**

* `mixed get(string $variable)`  
Get a config variable, encoded for safe use in HTML by default

* `set(string $variable [, mixed $value ])`  
Set a config variable
