<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

/**
 * Modular Extensions - HMVC
 *
 * Adapted from the CodeIgniter Core Classes
 * @link    http://codeigniter.com
 *
 * Description:
 * This library extends the CodeIgniter CI_Loader class
 * and adds features allowing use of modules and the HMVC design pattern.
 *
 * Install this file as application/third_party/MX/Loader.php
 *
 * @copyright   Copyright (c) 2011 Wiredesignz
 * @version     5.4
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 **/
class MX_Loader extends CI_Loader
{
    protected $_module;
    
    public $_ci_plugins = array();
    public $_ci_cached_vars = array();
    
    /** Initialize the loader variables **/
    public function initialize($controller = NULL) {
        
        if (is_a($controller, 'MX_Controller')) {   
            
            /* reference to the module controller */
            $this->controller = $controller;
            
            /* references to ci loader variables */
            foreach (get_class_vars('CI_Loader') as $var => $val) {
                if ($var != '_ci_ob_level') {
                    $this->$var =& CI::$APP->load->$var;
                }
            }
            
        } else {
            parent::initialize();
        }
        
        /* set the module name */
        $this->_module = CI::$APP->router->fetch_module();
        
        /* add this module path to the loader variables */
        $this->_add_module_paths($this->_module);
    }

    /** Add a module path loader variables **/
    public function _add_module_paths($module = '') {
        
        if (empty($module)) return;
        
        foreach (Modules::$locations as $location => $offset) {
            
            /* only add a module path if it exists */
            if (is_dir($module_path = $location.$module.'/') && ! in_array($module_path, $this->_ci_model_paths)) 
            {
                array_unshift($this->_ci_model_paths, $module_path);
            }
        }
    }   
    
    /** Load a module config file **/
    public function config($file = 'config', $use_sections = FALSE, $fail_gracefully = FALSE) {
        return CI::$APP->config->load($file, $use_sections, $fail_gracefully, $this->_module);
    }

    /** Load the database drivers **/
    public function database($params = '', $return = FALSE, $active_record = NULL) {
        
        if (class_exists('CI_DB', FALSE) AND $return == FALSE AND $active_record == NULL AND isset(CI::$APP->db) AND is_object(CI::$APP->db)) 
            return;

        require_once BASEPATH.'database/DB'.EXT;

        if ($return === TRUE) return DB($params, $active_record);
            
        CI::$APP->db = DB($params, $active_record);
        
        return CI::$APP->db;
    }

    /** Load a module helper **/
    public function helper($helper = array()) {
        
        if (is_array($helper)) return $this->helpers($helper);
        
        if (isset($this->_ci_helpers[$helper])) return;

        list($path, $_helper) = Modules::find($helper.'_helper', $this->_module, 'helpers/');

        if ($path === FALSE) return parent::helper($helper);

        Modules::load_file($_helper, $path);
        $this->_ci_helpers[$_helper] = TRUE;
    }

    /** Load an array of helpers **/
    public function helpers($helpers = array()) {
        foreach ($helpers as $_helper) $this->helper($_helper); 
    }

    /** Load a module language file **/
    public function language($langfile = array(), $idiom = '', $return = FALSE, $add_suffix = TRUE, $alt_path = '') {
        return CI::$APP->lang->load($langfile, $idiom, $return, $add_suffix, $alt_path, $this->_module);
    }
    
    public function languages($languages) {
        foreach($languages as $_language) $this->language($_language);
    }
    
    /** Load a module library **/
    public function library($library = '', $params = NULL, $object_name = NULL) {
        
        if (is_array($library)) return $this->libraries($library);      
        
        $class = strtolower(basename($library));

        if (isset($this->_ci_classes[$class]) AND $_alias = $this->_ci_classes[$class])
            return CI::$APP->$_alias;
            
        ($_alias = strtolower($object_name)) OR $_alias = $class;
        
        list($path, $_library) = Modules::find($library, $this->_module, 'libraries/');
        
        /* load library config file as params */
        if ($params == NULL) {
            list($path2, $file) = Modules::find($_alias, $this->_module, 'config/');    
            ($path2) AND $params = Modules::load_file($file, $path2, 'config');
        }   
            
        if ($path === FALSE) {
            
            $this->_ci_load_class($library, $params, $object_name);
            if ((substr(CI_VERSION,0,1)+0)<3)
                $_alias = $this->_ci_classes[$class];
            else
                CI::$APP->$_alias = $_alias = $this->_ci_classes[$class];
            
        } else {        
            
            Modules::load_file($_library, $path);
            
            $library = ucfirst($_library);
            CI::$APP->$_alias = new $library($params);
            
            $this->_ci_classes[$class] = $_alias;
        }
        
        return CI::$APP->$_alias;
    }

    /** Load an array of libraries **/
    public function libraries($libraries) {
        foreach ($libraries as $_library) $this->library($_library);    
    }

    /** Load a module model **/
    public function model($model, $object_name = NULL, $connect = FALSE) {
        
        if (is_array($model)) return $this->models($model);

        ($_alias = $object_name) OR $_alias = basename($model);

        if (in_array($_alias, $this->_ci_models, TRUE)) 
            return CI::$APP->$_alias;
            
        /* check module */
        list($path, $_model) = Modules::find(strtolower($model), $this->_module, 'models/');
        
        if ($path == FALSE) {
            
            /* check application & packages */
            parent::model($model, $object_name, $connect);
            
        } else {
            
            class_exists('CI_Model', FALSE) OR load_class('Model', 'core');
            
            if ($connect !== FALSE AND ! class_exists('CI_DB', FALSE)) {
                if ($connect === TRUE) $connect = '';
                $this->database($connect, FALSE, TRUE);
            }
            
            Modules::load_file($_model, $path);
            
            $model = ucfirst($_model);
            CI::$APP->$_alias = new $model();
            
            $this->_ci_models[] = $_alias;
        }
        
        return CI::$APP->$_alias;
    }

    /** Load an array of models **/
    public function models($models) {
        foreach ($models as $_model) $this->model($_model); 
    }

    /** Load a module controller **/
    public function module($module, $params = NULL) {
        
        if (is_array($module)) return $this->modules($module);

        $_alias = strtolower(basename($module));
        CI::$APP->$_alias = Modules::load(array($module => $params));
        return CI::$APP->$_alias;
    }

    /** Load an array of controllers **/
    public function modules($modules) {
        foreach ($modules as $_module) $this->module($_module); 
    }

    /** Load a module plugin **/
    public function plugin($plugin) {
        
        if (is_array($plugin)) return $this->plugins($plugin);      
        
        if (isset($this->_ci_plugins[$plugin])) 
            return;

        list($path, $_plugin) = Modules::find($plugin.'_pi', $this->_module, 'plugins/');   
        
        if ($path === FALSE AND ! is_file($_plugin = APPPATH.'plugins/'.$_plugin.EXT)) {    
            show_error("Unable to locate the plugin file: {$_plugin}");
        }

        Modules::load_file($_plugin, $path);
        $this->_ci_plugins[$plugin] = TRUE;
    }

    /** Load an array of plugins **/
    public function plugins($plugins) {
        foreach ($plugins as $_plugin) $this->plugin($_plugin); 
    }

    /** Load a module view **/
    public function view($view, $vars = array(), $return = FALSE) {
        list($path, $_view) = Modules::find($view, $this->_module, 'views/');
        
        if ($path != FALSE) {
            $this->_ci_view_paths = array($path => TRUE) + $this->_ci_view_paths;
            $view = $_view;
        }
        
        return $this->_ci_load(array('_ci_view' => $view, '_ci_vars' => $this->_ci_object_to_array($vars), '_ci_return' => $return));
    }

    public function _ci_is_instance() {}

    protected function &_ci_get_component($component) {
        return CI::$APP->$component;
    } 

    public function __get($class) {
        return (isset($this->controller)) ? $this->controller->$class : CI::$APP->$class;
    }

    public function _ci_load($_ci_data) {
        
        extract($_ci_data);
        
        if (isset($_ci_view)) {
            
            $_ci_path = '';
            
            /* add file extension if not provided */
            $_ci_file = (pathinfo($_ci_view, PATHINFO_EXTENSION)) ? $_ci_view : $_ci_view.EXT;
            
            foreach ($this->_ci_view_paths as $path => $cascade) {              
                if (file_exists($view = $path.$_ci_file)) {
                    $_ci_path = $view;
                    break;
                }
                
                if ( ! $cascade) break;
            }
            
        } elseif (isset($_ci_path)) {
            
            $_ci_file = basename($_ci_path);
            if( ! file_exists($_ci_path)) $_ci_path = '';
        }

        if (empty($_ci_path)) 
            show_error('Unable to load the requested file: '.$_ci_file);

        if (isset($_ci_vars)) 
            $this->_ci_cached_vars = array_merge($this->_ci_cached_vars, (array) $_ci_vars);
        
        extract($this->_ci_cached_vars);

        ob_start();

        if ((bool) @ini_get('short_open_tag') === FALSE AND CI::$APP->config->item('rewrite_short_tags') == TRUE) {
            echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($_ci_path))));
        } else {
            include($_ci_path); 
        }

        log_message('debug', 'File loaded: '.$_ci_path);

        if ($_ci_return == TRUE) return ob_get_clean();

        if (ob_get_level() > $this->_ci_ob_level + 1) {
            ob_end_flush();
        } else {
            CI::$APP->output->append_output(ob_get_clean());
        }
    }   
    
    /** Autoload module items **/
    public function _autoloader($autoload) {
        
        $path = FALSE;
        
        if ($this->_module) {
            
            list($path, $file) = Modules::find('constants', $this->_module, 'config/'); 
            
            /* module constants file */
            if ($path != FALSE) {
                include_once $path.$file.EXT;
            }
                    
            list($path, $file) = Modules::find('autoload', $this->_module, 'config/');
        
            /* module autoload file */
            if ($path != FALSE) {
                $autoload = array_merge(Modules::load_file($file, $path, 'autoload'), $autoload);
            }
        }
        
        /* nothing to do */
        if (count($autoload) == 0) return;
        
        /* autoload package paths */
        if (isset($autoload['packages'])) {
            foreach ($autoload['packages'] as $package_path) {
                $this->add_package_path($package_path);
            }
        }
                
        /* autoload config */
        if (isset($autoload['config'])) {
            foreach ($autoload['config'] as $config) {
                $this->config($config);
            }
        }

        /* autoload helpers, plugins, languages */
        foreach (array('helper', 'plugin', 'language') as $type) {
            if (isset($autoload[$type])){
                foreach ($autoload[$type] as $item) {
                    $this->$type($item);
                }
            }
        }   
            
        /* autoload database & libraries */
        if (isset($autoload['libraries'])) {
            if (in_array('database', $autoload['libraries'])) {
                /* autoload database */
                if ( ! $db = CI::$APP->config->item('database')) {
                    $db['params'] = 'default';
                    $db['active_record'] = TRUE;
                }
                $this->database($db['params'], FALSE, $db['active_record']);
                $autoload['libraries'] = array_diff($autoload['libraries'], array('database'));
            }

            /* autoload libraries */
            foreach ($autoload['libraries'] as $library) {
                $this->library($library);
            }
        }
        
        /* autoload models */
        if (isset($autoload['model'])) {
            foreach ($autoload['model'] as $model => $alias) {
                (is_numeric($model)) ? $this->model($alias) : $this->model($model, $alias);
            }
        }
        
        /* autoload module controllers */
        if (isset($autoload['modules'])) {
            foreach ($autoload['modules'] as $controller) {
                ($controller != $this->_module) AND $this->module($controller);
            }
        }
    }

    // Modified by Ivan Tcholakov, 12-OCT-2013.
    protected function _ci_load_class($class, $params = NULL, $object_name = NULL, $try_with_lcfirst = false)
    {
        $original = compact('class', 'params', 'object_name');

        // Get the class name, and while we're at it trim any slashes.
        // The directory path can be included as part of the class name,
        // but we don't want a leading slash
        $class = str_replace('.php', '', trim($class, '/'));

        // Was the path included with the class name?
        // We look for a slash to determine this
        if (($last_slash = strrpos($class, '/')) !== FALSE)
        {
            // Extract the path
            $subdir = substr($class, 0, ++$last_slash);

            // Get the filename from the path
            $class = substr($class, $last_slash);
        }
        else
        {
            $subdir = '';
        }

        if (!$try_with_lcfirst) {
            $class = ucfirst($class);
        } else {
            $class = lcfirst($class);
        }

        $subclass = APPPATH.'libraries/'.$subdir.config_item('subclass_prefix').$class.'.php';

        // Is this a class extension request?
        if (file_exists($subclass))
        {
            $baseclass = BASEPATH.'libraries/'.$subdir.$class.'.php';

            // A modification by Ivan Tcholakov, 07-APR-2013.
            // A fix for loaddnig Session library.
            //if ( ! file_exists($baseclass))
            if ($class != 'Session' && ! file_exists($baseclass))
            //
            {
                log_message('error', 'Unable to load the requested class: '.$class);
                show_error('Unable to load the requested class: '.$class);
            }

            // Safety: Was the class already loaded by a previous call?
            if (class_exists(config_item('subclass_prefix').$class, FALSE))
            {
                // Before we deem this to be a duplicate request, let's see
                // if a custom object name is being supplied. If so, we'll
                // return a new instance of the object
                // Modified by Ivan Tcholakov, 12-DEC-2013.
                if ($object_name != '')
                //
                {
                    $CI =& get_instance();
                    if ( ! isset($CI->$object_name))
                    {
                        return $this->_ci_init_class($class, config_item('subclass_prefix'), $params, $object_name);
                    }
                }

                log_message('debug', $class.' class already loaded. Second attempt ignored.');
                return;
            }

            // A modification by Ivan Tcholakov, 07-APR-2013.
            //include_once($baseclass);
            if ($class != 'Session')
            {
                include_once $baseclass;
            }
            //
            include_once $subclass;

            return $this->_ci_init_class($class, config_item('subclass_prefix'), $params, $object_name);
        }

        // Let's search for the requested library file and load it.
        foreach ($this->_ci_library_paths as $path)
        {
            $filepath = $path.'libraries/'.$subdir.$class.'.php';

            // Safety: Was the class already loaded by a previous call?
            if (class_exists($class, FALSE))
            {
                // Before we deem this to be a duplicate request, let's see
                // if a custom object name is being supplied. If so, we'll
                // return a new instance of the object
                // Modified by Ivan Tcholakov, 12-DEC-2013.
                //if ($object_name !== NULL)
                if ($object_name != '')
                //
                {
                    $CI =& get_instance();
                    if ( ! isset($CI->$object_name))
                    {
                        return $this->_ci_init_class($class, '', $params, $object_name);
                    }
                }

                log_message('debug', $class.' class already loaded. Second attempt ignored.');
                return;
            }
            // Does the file exist? No? Bummer...
            elseif ( ! file_exists($filepath))
            {
                continue;
            }

            include_once $filepath;
            return $this->_ci_init_class($class, '', $params, $object_name);
        }

        if (!$try_with_lcfirst) {
            $this->_ci_load_class($original['class'], $original['params'], $original['object_name'], true);
        }

        // One last attempt. Maybe the library is in a subdirectory, but it wasn't specified?
        // Modified by Ivan Tcholakov, 12-DEC-2013.
        //if ($subdir === '')
        if ($subdir == '')
        //
        {
            return $this->_ci_load_class($class.'/'.$class, $params, $object_name);
        }

        // If we got this far we were unable to find the requested class.
        log_message('error', 'Unable to load the requested class: '.$class);
        show_error('Unable to load the requested class: '.$class);
    }

    protected function _ci_init_class($class, $prefix = '', $config = FALSE, $object_name = NULL)
    {
        // Is there an associated config file for this class? Note: these should always be lowercase
        if ($config === NULL)
        {
            // Fetch the config paths containing any package paths
            $config_component = $this->_ci_get_component('config');

            if (is_array($config_component->_config_paths))
            {
                $found = FALSE;
                foreach ($config_component->_config_paths as $path)
                {
                    // We test for both uppercase and lowercase, for servers that
                    // are case-sensitive with regard to file names. Load global first,
                    // override with environment next
                    if (file_exists($path.'config/'.strtolower($class).'.php'))
                    {
                        include($path.'config/'.strtolower($class).'.php');
                        $found = TRUE;
                    }
                    elseif (file_exists($path.'config/'.ucfirst(strtolower($class)).'.php'))
                    {
                        include($path.'config/'.ucfirst(strtolower($class)).'.php');
                        $found = TRUE;
                    }

                    if (file_exists($path.'config/'.ENVIRONMENT.'/'.strtolower($class).'.php'))
                    {
                        include($path.'config/'.ENVIRONMENT.'/'.strtolower($class).'.php');
                        $found = TRUE;
                    }
                    elseif (file_exists($path.'config/'.ENVIRONMENT.'/'.ucfirst(strtolower($class)).'.php'))
                    {
                        include($path.'config/'.ENVIRONMENT.'/'.ucfirst(strtolower($class)).'.php');
                        $found = TRUE;
                    }

                    if (strpos($path, COMMONPATH) !== 0) {
                        break;
                    }
                }
                //
            }
        }

        if ($prefix === '')
        {
            if (class_exists(config_item('subclass_prefix').$class, FALSE))
            {
                $name = config_item('subclass_prefix').$class;
            }
            elseif (class_exists($class, FALSE))
            {
                $name = $class;
            }
            elseif (class_exists('CI_'.$class, FALSE))
            {
                $name = 'CI_'.$class;
            }
            else
            {
                $name = $class;
            }
            //
        }
        else
        {
            $name = $prefix.$class;
        }

        // Is the class name valid?
        if ( ! class_exists($name, FALSE))
        {
            log_message('error', 'Non-existent class: '.$name);
            show_error('Non-existent class: '.$name);
        }

        // Added by Ivan Tcholakov, 25-JUL-2013.
        $class = strtolower($class);
        //

        // Set the variable name we will assign the class to
        // Was a custom class name supplied? If so we'll use it
        if (empty($object_name))
        {
            $object_name = strtolower($class);
            if (isset($this->_ci_varmap[$object_name]))
            {
                $object_name = $this->_ci_varmap[$object_name];
            }
        }

        // Don't overwrite existing properties
        $CI =& get_instance();
        if (isset($CI->$object_name))
        {
            if ($CI->$object_name instanceof $name)
            {
                log_message('debug', $class." has already been instantiated as '".$object_name."'. Second attempt aborted.");
                return;
            }

            show_error("Resource '".$object_name."' already exists and is not a ".$class." instance.");
        }


        // Save the class name and object name
        // Modified by Ivan Tcholakov, 25-JUL-2013.
        //$this->_ci_classes[$object_name] = $class;
        $this->_ci_classes[$class] = $object_name;
        //

        // Instantiate the class
        $CI->$object_name = isset($config)
            ? new $name($config)
            : new $name();
    }
}

/** load the CI class for Modular Separation **/
(class_exists('CI', FALSE)) OR require dirname(__FILE__).'/Ci.php';