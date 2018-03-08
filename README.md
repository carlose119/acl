# CakePHP Acl Plugin

[![License](https://poser.pugx.org/cakephp/acl/license.svg)](https://packagist.org/packages/carlose119/acl)

Proyecto basado en el proyecto: (https://packagist.org/packages/abreu1234/acl)
Este proyecto es un de Fork de https://github.com/abreu1234/acl
Todo el reconocimiento al trabajo hecho por el usuario abreu1234

El plugin para el manejo de ACL en aplicaciones CakePHP.

## Instalando via composer

Usted puede instalar este plugin usando el composer
[Composer] (http://getcomposer.org). Agregue la siguiente dependencia en
su `composer.json` file:

```javascript
"require": {
	"carlose119/acl": "dev-master"
}
```

y ahora ejecute en su terminal `php composer.phar update`

Cargue el plugin añadiendo la siguiente línea en `config\bootstrap.php`:
```php
Plugin::load('Acl', ['bootstrap' => false, 'routes' => true]);
```

## Creando tablas

Para crear las tablas necesarias para el plugin usando `Migrations`
ejecute el siguiente comando en su terminal:

```
bin/cake migrations migrate -p Acl
```

## Cargando complemento Auth

Debe iniciar el complemento `Auth` del cakephp

[(Auth cakephp)](http://book.cakephp.org/3.0/en/controllers/components/authentication.html)
[(Auth tutorial)](http://book.cakephp.org/3.0/en/tutorials-and-examples/blog-auth-example/auth.html)

## Configuración básica

Para cargar el complemento usted debe agregar el nombre de su controlador de usuarios
en `Controller\AppController.php` de su aplicación

```php
$this->loadComponent('Acl.Acl', ['controllers' =>['user'=>'Users']]);
```

Si utiliza grupos agregar el nombre del controlador de grupo también

```php
$this->loadComponent('Acl.Acl', ['controllers' =>['user'=>'Users','group'=>'Groups']]);
```

## Sincronizar controladores de plugins
Para sincronizar los controllers de plugins basta con añadir la configuración a índice  `plugins`
```php
$this->loadComponent('Acl.Acl', 
	[
		'controllers' =>['user'=>'Users','group'=>'Groups'],
		'plugins' => ['PluginName']
	]

);
```
Por defecto el plugin este plugin sincronizará los controles

## Ignorar carpetas y archivos
Para omitir alguna carpeta o archivo durante la sincronización basta con añadir la configuración el índice `ignore`
con la siguiente sintaxis `Prefixo->Pasta/Arquivo->Action`. Para omitir todos los prefijos o carpetas de un prefijo
adicione `*`
```php
$this->loadComponent('Acl.Acl', [
	'controllers' =>['user'=>'Users','group'=>'Groups'],
	'plugins' => ['PluginName'],
	'ignore' => [
		'*' => [
	            '.','..','Component','AppController.php','empty',
	            '*'  => ['beforeFilter', 'afterFilter', 'initialize'],
	            'Permission'  => ['add']
	        ],
	        'Admin' => [
	        	'Users' => ['delete']
	        ]
        ]
]);
```

## Dando permiso

Para dar permiso a algún controlador sin necesidad de la base de datos
agregue las líneas siguientes. 

```php
$this->loadComponent('Acl.Acl', [
            'authorize' => [
                '/' => [
                    'Users' => ['index'],
                ]
            ],
            'controllers' =>['user'=>'Users']
        ]);
```

Utilizar el índice `authorize` con la siguiente sintaxis `Prefixo->Controller->Action` 
en el ejemplo anterior estando dando permiso al Controller `User` e Action `index`.
Para la aplicación raíz sin prefijo utilizar `/`

Si necesita autorizar un controlador dentro de un prefijo usar el nombre del prefijo después de `/` 

```php
$this->loadComponent('Acl.Acl', [
            'authorize' => [
                '/' => [
                    'Users' => ['index'],
                ],
                '/Admin' => [
                    'Users' => ['add'],
                ]
            ],
            'controllers' =>['user'=>'Users']
        ]);
```
En el ejemplo anterior estamos dando permiso al Controlador `User` y Action` add` del prefijo `Admin`

Si necesita autorizar un plugin utilizar la siguiente sintaxis `Plugin.Prefix` user `/` para la raíz del plugin
```php
$this->loadComponent('Acl.Acl', [
            'authorize' => [
                '/' => [
                    'Users' => ['index'],
                ],
                '/Admin' => [
                    'Users' => ['add'],
                ],
                'Acl./' => [
                    'Permission' => ['index','synchronize'],
                    'UserGroupPermission' => ['index','getPermission','addAjax']
                ],
            ],
            'controllers' =>['user'=>'Users']
        ]);
```
El ejemplo de arriba por seguridad sólo utilice hasta que haya agregado permisos a algún usuario o grupo.
Después de quitar las líneas
```php
'Acl./' => [
          'Permission' => ['index','synchronize'],
          'UserGroupPermission' => ['index','getPermission','addAjax']
      ],
```

## Método isAuthorized
Para realizar la validación del usuario o grupo, utilice el método isAuthorized del complemento Auth. Agregue en el 
archivo `AppController.php` el siguiente código.
```php
    public function isAuthorized($user)
    {
        if(!$this->Acl->check()) {
            $this->Flash->error(__('User or group no access permission!'));
            return false;
        }

        return true;
    }
```

## Sincronización 
Para sincronizar los controllers y actions basta ir a la dirección: `/acl/permission` hacer clic en el enlace de sincronización
es importante que el usuario tenga permiso de acceso al controlador `Permission` y Actions `index` y `synchronize`

## Administrar permisos
Para gestionar los permisos de los usuarios o grupos basta con ir a la dirección: `/acl/user-group-permission`
Seleccione el usuario o el grupo y los permisos.
Para funcionar el usuario hay que haber sincronizado los permisos y tener permiso de acceso al usuario
controlador `UserGroupPermission` y Actions `index`, `getPermission` y `addAjax`
