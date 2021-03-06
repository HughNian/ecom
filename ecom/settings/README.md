# ecom-settings

The settings component of ecom.

## Installation

The recommended way to install ecom-settings is via [composer](http://getcomposer.org).

1. Add a single line to composer.json:

    ```
    "require": {
        ...
        "ecom/settings": "dev-master"
    	...
    }
    ```
    
2. Run composer to update denpendencies:

    ```
    $ cd /path/to/project
    $ php composer.phar update
    ```

## Features

* Multiple storage backend support, such as RDMBS database and Redis.
* Custom storage backend support, by implement the StorageInterface interface.
* ArrayAccess support, you can access settings just like arrays.


## Usage

1. Import the table schema located in data directory

2. Set up settings as application component

  ```php
  //...
  'aliases' => array(
      'ecom' => 'application.vendors.ecom',
  ),
  'components' => array(
      //...
      'settings' => array(
          'class' => 'ecom\settings\Settings',
      ),
      //...
  ),
  //...
  ```
  
3. Store settings via settings component.

  ```php
  $settings = Yii::app()->getSettings();
  $settings->set('foo', 'value of foo');
  $bar = $settings->get('bar', 'default value');
  $settings->delete('foo');
  
  //you can also using settings like arrays
  $settings['foo'] = 'value of foo';
  $bar = $settings['bar'];
  unset($settings['foo']);
  ```

## Authors

* Jin Hu <bixuehujin@gmail.com>


## Licence

* MIT
