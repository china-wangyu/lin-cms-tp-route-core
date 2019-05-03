<?php


namespace LinCmsTp;

use LinCmsTp\exception\ParamException;
use LinCmsTp\reflex\Reflex;
use LinCmsTp\validate\Param as Permission;
/**
 * Class Param 检验路由的参数
 * @package LinCmsTp\middleware
 */
class Param
{
    /**
     * @var mixed 验证规则或者验证模型
     */
    protected $rule = [];
    /**
     * @var array 验证参数名称定义
     */
    protected $field = [];

    /**
     * 权限验证
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed
     * @throws ParamException
     */
    public function handle(\think\Request $request, \Closure $next)
    {
        $auth = (new Permission($this->rule,$request,$this->field))->check();
        if (!$auth) {
            throw new ParamException();
        }
        return $next($request);
    }

    public function setReflexParamRule(\think\Request $request){
        $controller = str_replace('.',DIRECTORY_SEPARATOR,$request->controller());
        $namespace = env('APP_NAMESPACE').DIRECTORY_SEPARATOR.$request->module().DIRECTORY_SEPARATOR.
            config('url_controller_layer').DIRECTORY_SEPARATOR.$controller;
        $param = (new Reflex($namespace,$request->action()))->get('param',[
            ['name','doc','rule'],
            ['validateClass']
        ]);
        if (!isset($param[0]['validateClass'])){
            foreach ($param as $item){
                $this->rule[$item['name']] = $item['rule'];
                $this->field[$item['name']] = $item['doc'];
            }
        }else{
            $this->rule = $param[0]['validateClass'];
        }
    }
}