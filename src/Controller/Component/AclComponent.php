<?php
namespace Acl\Controller\Component;

use Cake\Controller\Component;
use Cake\ORM\TableRegistry;
use ReflectionClass;
use ReflectionMethod;
use Cake\Core\App;

/**
 * Acl component
 */
class AclComponent extends Component
{

    /**
     * Controllers and actions allowed for all users
     * 
     * @var array Prefix => Controller => Action
     */
    private $_authorized = [];
    
    /**
     * Controllers and actions ignored during synchronization
     * 
     * @var Array Prefix => Controller => Action
     */
    private $_sync_ignore_list = [
        '*' => [
            '.','..','Component','AppController.php','empty',
            '*'  => ['beforeFilter', 'afterFilter', 'initialize']
        ],
    ];
    
    /**
     * Controllers groups and users
     * 
     * @var Array 
     */
    private $controllers = ['group'=>'','user'=>''];

    public function initialize( array $config )
    {
        if( isset($config['authorize']) )
            $this->_authorized = array_merge_recursive($this->_authorized, $config['authorize']);
        
        if( isset($config['ignore']) )
            $this->_sync_ignore_list = array_merge_recursive($this->_sync_ignore_list, $config['ignore']);
        
        if( isset($config['controllers']) ) {
            $this->controllers = array_merge($this->controllers, $config['controllers']);
        }
        
        if( empty($this->controllers['user']) )
            die('Acl: Controller user not set');

    }

    /**
     * Checks whether the user or group is allowed access
     * @return bool
     */
    public function check()
    {
    	$action = $this->request->param('action');
        $controller = $this->request->param('controller');
        $prefix = ($this->request->param('prefix') != false ) ? $this->request->param('prefix') : '';
        
        if( isset($this->_authorized[$prefix][$controller]) &&
            in_array($action, $this->_authorized[$prefix][$controller]) ) return true;
        
        $user_id = $this->request->session()->read('Auth.User.id');
        $group_id = -1;
        if( isset($this->controllers['group']) ) {
            $User = TableRegistry::get($this->controllers['user']);
            $group_id = $User->get($user_id)->group_id;
        }
        
        $UserGroupPermission = TableRegistry::get('UserGroupPermission');
        $Permission = TableRegistry::get('Permission');

        $unique_string = $prefix . '/' . $controller . '->' . $action;
        $permission_id = $Permission->find()
            ->select(['id'])
            ->where(['unique_string' => $unique_string])->first();
        if( is_null($permission_id) ) return false;

        $allow = $UserGroupPermission->find()
            ->select(['allow'])
            ->where(
                [
                    'group_or_user'     => 'user',
                    'group_or_user_id'  => $user_id,
                    
                ])
            ->orWhere(
                [
                    'group_or_user'     => 'group',
                    'group_or_user_id'  => $group_id
                ])->andWhere(['permission_id' => $permission_id->id])
            ->order(['allow'=>'DESC'])->first();
        if( is_null($allow) ) return false;
            
        return $allow->allow;
    }
    
    /**
     * Synchronizes all controllers and existing actions to the database
     * 
     * @param boolean/String $prefix
     * @param boolean/String $plugin
     * @param array $permission_ids
     * @return array $permission_ids
     */
    public function synchronize( $prefix = false, $plugin = false, array $permission_ids = [] )
    {
        $classname = '';
        if( !$plugin ) {
            $path = App::path('Controller/'.$prefix)[0];            
        } else {
            if($prefix) {
                $path = App::path('Controller/'.$prefix, $plugin)[0];
                $classname = $plugin.'.';
            } else {
                $path = App::path('Plugin')[0];
            }            
        }
        $type_prefix = ( $prefix === '/' || is_bool($prefix) ) ? '' : '/'.$prefix;
                 
        $Permission = TableRegistry::get('Permission');
        
        $files = scandir($path);
        $permission_prefix = '';
        foreach($files as $file) {
            if(in_array($file, $this->_sync_ignore_list['*']) ||
                ( isset($this->_sync_ignore_list[$prefix]) && in_array($file, $this->_sync_ignore_list[$prefix]) )
            ) continue;

            if( is_dir($path.$file) ) {
                if( $prefix || !$plugin )
                    $permission_ids = $this->synchronize($file, $plugin, $permission_ids);
                else if( $plugin )
                    $permission_ids = $this->synchronize('/', $file, $permission_ids);
                continue;
            }
            
            $controller_name = str_replace('Controller', '', explode('.', $file)[0]);
            $class_name = App::classname($classname.$controller_name, 'Controller'.$type_prefix, 'Controller');
            $class = new ReflectionClass($class_name);
            $all_actions = $class->getMethods(ReflectionMethod::IS_PUBLIC);
            
            foreach($all_actions as $action) {
                $unique_string = '';
                if( $plugin ) {
                    $permission_prefix = $unique_string .= $plugin.'.';
                }
                if( $prefix )
                    $permission_prefix = $unique_string .= $prefix;
                
                if($action->class != $class_name || in_array($action->name, $this->_sync_ignore_list['*']['*']) ||
                    ( isset($this->_sync_ignore_list['*'][$controller_name]) && in_array($action->name, $this->_sync_ignore_list['*'][$controller_name]) ) ||                      
                    ( isset($this->_sync_ignore_list[$permission_prefix]['*']) && in_array($action->name, $this->_sync_ignore_list[$permission_prefix]['*']) ) ||                      
                    ( isset($this->_sync_ignore_list[$permission_prefix][$controller_name]) && in_array($action->name, $this->_sync_ignore_list[$permission_prefix][$controller_name]) )
                ) continue;
                
                $unique_string .= '/'.$controller_name . '->' . $action->name;
                $permission_id = $Permission->find()
                    ->select(['id'])
                    ->where(['unique_string' => $unique_string])
                    ->first();

                if (is_null($permission_id)) {
                    $new_permission = $Permission->newEntity();
                    $new_permission->action = $action->name;
                    $new_permission->controller = $controller_name;
                    $new_permission->prefix = $permission_prefix;
                    $new_permission->unique_string = $unique_string;

                    if ($Permission->save($new_permission))
                        array_push($permission_ids, $new_permission->id);
                    
                } else {
                    array_push($permission_ids, $permission_id->id);
                }
            }
        }

        if( !$plugin && !$prefix ) {
            $permission_ids = $this->synchronize('', true, $permission_ids);
            $Permission->deleteAll(['id NOT IN'=>$permission_ids]);
        }

        return $permission_ids;
    }
    
    public function getControllers() 
    {
        return $this->controllers;
    }

}
