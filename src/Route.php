<?php


namespace LinCmsTp;

use LinCmsTp\exception\RouteException;
use LinCmsTp\reflex\Reflex;
use think\facade\Route as Router;

class Route
{
    /** @var Route $ins */
    protected static $ins;
    /** @var array $modules 模块集合 */
    protected $modules = [];
    /**
     * @var string $path 目录
     */
    protected $path;
    /**
     * @var array $reflexClassMap 反射对象集合
     */
    protected $reflexClassMap = [];
    /**
     * @var string $np 命名空间参数，采取（namespace param）的首字母
     */
    protected $np;
    /** @var object $class 类 */
    protected $class;
    /** @var string $action 方法 */
    protected $action;
    // 设置默认.php
    private $ext = '.php';
    // 默认的所有模块
    private $default_module = ['api'];

    public function __construct(string $module = '')
    {
        if (!in_array($module, $this->modules)) {
            !empty($module) && array_push($this->modules, $module);
        }
    }

    // 获取类实例
    protected static function getIns(string $module = ''): self
    {
        if (!isset(static::$ins)) {
            static::$ins = new self($module);
        }
        return static::$ins;
    }

    /**
     * 注册类路由
     * @param string $namespace
     * @param array $middleware
     * @throws \LinCmsTp\exception\RouteException
     */
    public static function cls(string $namespace, array $middleware)
    {
        try {
            if (PHP_SAPI == 'cli') return;
            $ins = static::getIns();
            empty($ins->np) && $ins->setNamespaceParam($namespace);
            $urlParam = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
            $actions = $ins->getClassActions($namespace);
            foreach ($actions as $action) {
                if (isset($urlParam[1]) and strtolower($ins->np['class']) == strtolower($urlParam[1])) {
                    static::fuc($namespace, $action, $middleware);
                }
            }
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }

    /**
     * 注册方法路由
     * @param string $namespace 类命名空间
     * @param string $action 方法名称
     * @param array $middleware 中间件集合
     * @throws RouteException
     */
    public static function fuc(string $namespace, string $action, array $middleware)
    {
        try {
            if (PHP_SAPI == 'cli') return;
            $ins = static::getIns();
            empty($ins->np) && $ins->setNamespaceParam($namespace);
            $ins->action = $action;
            $ins->setActionRoute(new \ReflectionClass($namespace), $ins->action);
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }

    // 初始化路由操作
    public static function init(string $module = ''): void
    {
        try {
            if (PHP_SAPI == 'cli') return;
            $ins = static::getIns($module);
            empty($ins->modules) && $ins->modules = $ins->default_module;
            $ins->setModulesReflexClassMap();
            $ins->setModuleRoute();
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }

    // 设置模块反射类地图
    private function setModulesReflexClassMap(): void
    {
        if (empty($this->modules)) return;
        foreach ($this->modules as $module) {
            $this->setVersionReflexClassMap($module);
        }
    }

    // 设置路由控制器前缀的反射对象
    private function setVersionReflexClassMap(string $module): void
    {
        try {
            $moduleMap = [];
            $this->path = env('APP_PATH') . $module . DIRECTORY_SEPARATOR . config('url_controller_layer');
            $classMaps = glob($this->path . '/*/*' . $this->ext);
            foreach ($classMaps as $class) {
                $classname = strtolower(basename(trim($class, $this->ext)));
                if (in_array($classname, explode('/', trim(strtolower($_SERVER['REQUEST_URI']), '/'))) === false) continue;
                $moduleMap[$classname] = new \ReflectionClass($this->getClassNamespace($class));
            }
            $this->reflexClassMap[$module] = $moduleMap;
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }

    // 设置模块路由
    private function setModuleRoute(): void
    {
        foreach ($this->reflexClassMap as $key => $module) {
            foreach ($module as $classname => $reflexClass) {
                if (in_array($classname, explode('/', trim(strtolower($_SERVER['REQUEST_URI']), '/'))) === false) continue;
                $this->class = $classname;
                $this->setClassRoute($reflexClass);
            }
        }
    }

    // 设置类路由
    private function setClassRoute(\ReflectionClass $reflectionClass): void
    {
        empty($this->np) && $this->setNamespaceParam($reflectionClass->getName());
        $actionArr = $this->getClassActions($reflectionClass->getName());
        foreach ($actionArr as $item) {
            $this->action = $item;
            $this->setActionRoute($reflectionClass, $item);
        }
    }

    // 设置方法路由
    private function setActionRoute(\ReflectionClass $reflectionClass, string $action): void
    {
        try {
            $reflex = new Reflex($reflectionClass);
            $middleware = $reflex->get('middleware');
            $group = $reflex->get('route', ['rule']);
            $route = $reflex->setAction($action)->get('route', ['rule', 'method']);
            if (empty($route)) return;
            if (!empty($group)) {
                if (empty($route[0]['rule']) or substr($route[0]['rule'], 0, 1) != '/') {
                    $route[0]['rule'] = $group[0]['rule'] . '/' . $route[0]['rule'];
                }
            }
            $this->setRoute($route[0]['rule'], $route[0]['method'], empty($middleware) ? [] : $middleware[0]);
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }


    // 设置路由
    private function setRoute(string $rule, string $method, array $middleware = [])
    {
        try {
            if ($_SERVER['REQUEST_URI'] == '/' . $rule and $_SERVER['REQUEST_METHOD'] == strtoupper($method)) {
                Router::rule(
                    $rule,
                    $this->np['module'] . '/' . $this->np['version'] . '.' . $this->np['class'] . '/' . $this->action,
                    $method
                )->middleware($middleware)->allowCrossDomain();
            }
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }

    /**
     * 获取对象所有的自身方法
     * @return array
     */
    private function getClassActions(string $namespace): array
    {
        $parentActions = get_class_methods(get_parent_class($namespace));
        $objectActions = get_class_methods($namespace);
        if (empty($parentActions)) return $objectActions;
        $actions = array_diff($objectActions, $parentActions);
        return empty($actions) ? [] : $actions;
    }

    /**
     * 获取类命名空间
     * @param string $classPath
     * @return string
     */
    private function getClassNamespace(string $classPath): string
    {
        $namespace = DIRECTORY_SEPARATOR . env('APP_NAMESPACE') . DIRECTORY_SEPARATOR . str_replace(env('APP_PATH'), '', trim($classPath, '.php'));
        $namespace = str_replace('/', '\\', $namespace);
        return $namespace;
    }

    /**
     * 获取命名空间所含有的所有参数
     * @param string $namespace
     * @return array
     */
    private function setNamespaceParam(string $namespace): void
    {
        $array = [];
        $array['class'] = basename($namespace);
        $paresThinkNamespace = str_replace(env('APP_NAMESPACE'), '', $namespace);
        $paresClassNamespace = str_replace('\\' . $array['class'], '', $paresThinkNamespace);
        $paresControllerNamespace = str_replace('\\' . config('url_controller_layer'), '', $paresClassNamespace);
        $namespaceArray = explode('\\', trim($paresControllerNamespace, '\\'));
        $array['module'] = isset($namespaceArray[0]) ? $namespaceArray[0] : '';
        $array['version'] = isset($namespaceArray[1]) ? $namespaceArray[1] : '';
        $this->np = $array;
    }
}