# lin-cms-tp5-reflex-core
LinCms TP5.1 的反射类核心模块封装，含路由注册，路由及请求类型验证，路由参数验证中间件，方法注释参数提取器

* 反射路由模式
* 优化路由注册
* 反射参数验证
* 简洁
* 优秀

# `composer`安装说明

```php
composer require lin-cms-tp5/reflex-core
```
# 使用说明


## 反射路由模式

- 需要更改route.php文件注册路由方式

```php
use LinCmsTp\Route as LinRoute;

// 注册类路由
LinRoute::cls(
    'app\api\controller\cms\User',
    ['Auth','Validate']
);
// 注册方法路由
LinRoute::fuc(
    'app\api\controller\cms\User',
    'login',
    ['Auth','Validate']
);
```

- 需要在API方法注释增加`@route('路由','请求类型')`

```php
/**
 * 账户登陆
 * @route('cms/user/login','post')
 * @param Request $request
 * @param('\app\api\validate\user\LoginForm')
 * @return array
 * @throws \think\Exception
 */
public function login(Request $request)
{
    $params = $request->post();
    $user = UserModel::verify($params['nickname'], $params['password']);
    $result = Token::getToken($user);
    logger('登陆领取了令牌', $user['id'], $user['nickname']);
    return $result;
}
```

## 反射参数验证

- 需要在系统`config`配置`middleware.php`

```php
<?php
return [
    // 默认中间件命名空间
    'default_namespace' => 'app\\http\\middleware\\',
    'linParam' => LinCmsTp\Param::Class
];
```

- 需要在系统`route`配置`route.php`

```php
// 注册类路由
LinRoute::cls(
 'app\api\controller\cms\User',
 ['linParam']
);
```

- 配置方法注释参数验证，有两种方式

  1 使用@param('参数名','参数注释','参数规则')，进行单个参数验证

    '参数规则' 对应TP的验证规则，例如：@param('id','ID','require|max:1000|min:1')

  2 使用@param('验证器的命名空间'),进行方法验证

    例如：@param('\app\api\validate\user\LoginForm') 相当于调用的\app\api\validate\user\LoginForm去验证