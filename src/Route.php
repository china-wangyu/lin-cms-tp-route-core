<?php


namespace LinCmsTp;

use LinCmsTp\reflex\Reflex;
use think\facade\Route as Router;
class Route
{

    /**
     * 注册类路由
     * @param string $namespace
     * @param array $middleware
     * @throws \WangYu\exception\ReflexException
     */
    public static function cls(string $namespace,array $middleware){
        if (PHP_SAPI == 'cli') return;
        $data = static::paresNamespace($namespace);
        $urlParam = explode('/',trim($_SERVER['REQUEST_URI'],'/'));
        $actions = static::getObjectActions($namespace);
        foreach ($actions as $action){
            if (strtolower($data['class']) == strtolower($urlParam[1])){
                static::fuc($namespace,$action,$middleware,$data);
            }
        }
    }

    /**
     * 注册方法路由
     * @param string $namespace 类命名空间
     * @param string $function 方法名称
     * @param array $middleware 中间件集合
     * @throws \WangYu\exception\ReflexException
     */
    public static function fuc(string $namespace,string $function,array $middleware,array $data){
        if (PHP_SAPI == 'cli') return;
        $route = (new Reflex($namespace,$function))->get('route',['rule','method']);
        if ($route[0]['rule'] != trim($_SERVER['REQUEST_URI'],'/')) return;
        !empty($route[0]['rule']) && Router::rule(
            $route[0]['rule'],
            $data['module'].'/'.$data['version'].'.'.$data['class'].'/'.$function,
            $route[0]['method']
        )->middleware($middleware)->allowCrossDomain();
    }

    /**
     * 获取对象所有的自身方法
     * @return array
     */
    private static function getObjectActions(string $namespace):array {
        $parentActions = get_class_methods(get_parent_class($namespace));
        $objectActions = get_class_methods($namespace);
        if (empty($parentActions)) return $objectActions;
        $actions = array_diff($objectActions,$parentActions);
        return empty($actions) ? [] : $actions;
    }

    /**
     * 获取命名空间所含有的所有参数
     * @param string $namespace
     * @return array
     */
    public static function paresNamespace(string $namespace): array {
        $array = [];
        $array['class'] = basename($namespace);
        $paresThinkNamespace = str_replace(env('APP_NAMESPACE'),'',$namespace);
        $paresClassNamespace = str_replace('\\'.$array['class'],'',$paresThinkNamespace);
        $paresControllerNamespace = str_replace('\\'.config('url_controller_layer'),'',$paresClassNamespace);
        $namespaceArray = explode('\\',trim($paresControllerNamespace,'\\'));
        $array['module'] = isset($namespaceArray[0])?$namespaceArray[0]:'';
        $array['version'] = isset($namespaceArray[1])?$namespaceArray[1]:'';
        return $array;
    }
}