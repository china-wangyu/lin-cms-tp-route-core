<?php


namespace LinCmsTp5\validate;

use think\Validate;
use LinCmsTp5\exception\ParamException;
class Param
{
    public $rule;
    public $field;
    public $request;

    public function __construct($rule,Request $request,array $field = [])
    {
        $this->request = $request;
        $this->rule = $rule;
        $this->field = $field;
    }

    public function check(){
        if (is_string($this->rule)) {
           $res = (new $this->rule)->check($this->request->param());
        }else{
            $validate = (new $this->rule)->make($this->rule,[],$this->field);
            $res = $validate->check($this->request->param());
        }
        if(!$res){
            $e = new ParamException([
                'message' => $validate->getError(),
            ]);
            throw $e;
        }
        return true;
    }
}