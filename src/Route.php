<?php
/**
 * Created by User: wene<china_wangyu@aliyun.com> Date: 2019/5/17 Time: 10:00
 */

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
    /** @var  $class 类 */
    protected $class;
    /** @var string $action 方法 */
    protected $action;
    // 设置默认.php
    private $ext = '.php';
    // 默认的所有模块
    private $default_module = ['api'];
    // 中间件参数
    protected $middleware = [];
    // 路由规则
    protected $rule;
    // 方法请求
    protected $method;
    // 设置路由地址
    protected $route;

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
     * @param string $class
     * @param array $middleware
     * @throws \LinCmsTp\exception\RouteException
     */
    public static function cls(string $class, array $middleware = [])
    {
        try {
            if (PHP_SAPI == 'cli') return;
            $ins = static::getIns();
            $ins->class = $class;
            $ins->middleware = $middleware;
            $ins->setClassRoute();
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }

    /**
     * 注册方法路由
     * @param string $class 类命名空间
     * @param string $action 方法名称
     * @param array $middleware 中间件集合
     * @throws RouteException
     */
    public static function fuc(string $class, string $action, array $middleware = [])
    {
        try {
            if (PHP_SAPI == 'cli') return;
            $ins = static::getIns();
            $ins->class = $class;
            $ins->action = $action;
            $ins->middleware = $middleware;
            $ins->setActionRoute();
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
                $moduleMap[$classname] = $this->getClassNamespace($class);
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
                $this->class = $reflexClass;
                $this->setClassRoute();
            }
        }
    }

    // 设置类路由
    private function setClassRoute(): void
    {
        $actionArr = $this->getClassActions();
        foreach ($actionArr as $item) {
            $this->action = $item;
            $this->setActionRoute();
        }
    }

    // 设置方法路由
    private function setActionRoute(): void
    {
        try {
            // 获取反射类
            $reflex = new Reflex($this->class);
            // 获取反射类注释中设置的@middleware
            $middleware = $reflex->get('middleware');
            !empty($middleware) && $this->middleware = $middleware[0];
            // 获取类注释的路由前缀
            $group = $reflex->get('route', ['rule']);
            // 获取方法注释的路由
            $route = $reflex->setAction($this->action)->get('route', ['rule', 'method']);
            if (empty($route)) return;
            // 设置全局的路由规则，和路由请求
            $this->setActionRouteRule($route[0]['rule'], isset($group[0]['rule']) ? $group[0]['rule'] : '');
            $this->setActionRouteMethod($route[0]['method']);
            // 设置全局的参数
            $this->setNamespaceParam();
            // 设置 TP路由
            $this->setRoute();
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }

    // 设置路由请求方式
    private function setActionRouteMethod(string $method):void
    {
        $this->method = $method;
    }

    // 获取路由规则，去掉多余/的
    private function setActionRouteRule(string $rule, string $group = ''):void
    {
        if (!empty($group)) {
            if (empty($rule) or substr($rule, 0, 1) != '/') {
                $rule = $group . '/' . $rule;
            }
        }
        $rule = ltrim($rule, '\/..\/');
        $this->rule = '/' . $rule;
    }


    // 设置路由
    private function setRoute():void
    {
        try {
            if (!empty($this->rule)) {
                if (strtoupper($_SERVER['REQUEST_METHOD']) == strtoupper($this->method) or strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS') {
                    Router::rule(
                        $this->rule,
                        $this->route,
                        $this->method
                    )->middleware($this->middleware)->allowCrossDomain();
                }
            }
        } catch (\Exception $exception) {
            throw new RouteException(['message' => $exception->getMessage()]);
        }
    }

    /**
     * 获取对象所有的自身方法
     * @return array
     */
    private function getClassActions(): array
    {
        $parentActions = get_class_methods(get_parent_class($this->class));
        $objectActions = get_class_methods($this->class);
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
     */
    private function setNamespaceParam(): void
    {
        $array = [];
        $array['class'] = basename($this->class);
        $paresThinkNamespace = str_replace(env('APP_NAMESPACE'), '', $this->class);
        $paresClassNamespace = str_replace('\\' . $array['class'], '', $paresThinkNamespace);
        $paresControllerNamespace = str_replace('\\' . config('url_controller_layer'), '', $paresClassNamespace);
        $namespaceArray = explode('\\', trim($paresControllerNamespace, '\\'));
        $array['module'] = isset($namespaceArray[0]) ? $namespaceArray[0] : '';
        $array['version'] = isset($namespaceArray[1]) ? $namespaceArray[1] : '';
        $this->route = $array['module'] . '/' . $array['version'] . '.' . $array['class'] . '/' . $this->action;
    }
}