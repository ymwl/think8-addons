<?php

declare(strict_types=1);

namespace think\addons;

use think\helper\Str;
use think\facade\Event;
use think\facade\Config;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;

class Route
{
    /**
     * 插件路由执行方法
     * 该方法用于处理插件的路由请求,通过解析请求中的路由信息,找到对应的插件、控制器和操作,并执行相应的逻辑
     * 如果插件、控制器或操作不存在,或者插件被禁用,将抛出相应的异常
     * 在执行操作之前,会触发一系列的事件,允许其他地方对插件的请求进行干预
     * @return mixed 返回执行操作的结果
     * @throws HttpException 如果插件、控制器或操作不存在,或者插件被禁用,将抛出HTTP异常
     */
    public static function execute()
    {
        // 获取应用程序实例
        $app = app();
        // 安全检查：仅允许在 index 应用中执行插件路由，防止在 admin/install 等管理应用中误触发
        $appName = $app->http->getName();
        if (!in_array($appName, ['index'])) {
            throw new HttpException(404, lang('addon not available in admin context'));
        }
        // 获取当前请求对象
        $request = $app->request;
        // 从路由中获取插件、控制器和操作的名称
        $addon = $request->route('addon');
        $controller = $request->route('controller');
        $action = $request->route('action');
        // 检查插件、控制器和操作的名称是否为空,如果为空,抛出HTTP异常
        if (empty($addon) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('addon can not be empty'));
        }
        // 触发addons_begin事件,可以在事件处理程序中进行一些全局的初始化操作
        // 注意: 事件触发必须在参数验证之后,确保只在合法的插件请求中触发
        Event::trigger('addons_begin', $request);
        // 设置请求的插件、控制器和操作属性
        $request->addon = $addon;
        // 设置当前请求的控制器、操作
        $request->setController($controller)->setAction($action);
        // 获取插件的信息,如果插件不存在,抛出HTTP异常
        $info = get_addons_info($addon);
        if (!$info) {
            throw new HttpException(404, lang('addon %s not found', [$addon]));
        }
        // 检查插件是否被禁用,如果被禁用,抛出HTTP异常
        if (!$info['status']) {
            throw new HttpException(404, lang('addon %s is disabled', [$addon]));
        }
        // 触发addon_module_init事件,可以在事件处理程序中进行一些插件相关的初始化操作
        Event::trigger('addon_module_init', $request);
        // SEO规范化:经原生插件路由(/addons/...)访问且插件伪静态规则可命中时,301重定向到伪静态地址
        self::redirectToRewrite($request, $addon, $controller, $action);
        // 根据插件和控制器的名称获取插件控制器的类名,如果类名不存在,抛出HTTP异常
        $class = get_addons_class($addon, 'controller', $controller);
        if (!$class) {
            throw new HttpException(404, lang('addon controller %s not found', [Str::studly($controller)]));
        }
        // 设置视图的路径为插件的视图目录
        $config = Config::get('view');
        $config['view_path'] = $app->addons->getAddonsPath() . $addon . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
        Config::set($config, 'view');
        // 创建插件控制器的实例,如果实例创建失败,抛出HTTP异常
        try {
            $instance = new $class($app);
        } catch (HttpResponseException $e) {
            // 控制器构造/初始化期间的业务跳转(如 error/success/redirect)需放行,
            // 由框架正常输出响应,不能被误判为"控制器未找到"
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException(404, lang('addon controller %s not found', [Str::studly($controller)]));
        }
        // 初始化变量,用于存储传递给操作方法的参数
        $vars = [];
        // 检查是否可以调用指定的操作方法,如果可以,记录调用的方法
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 如果操作方法不存在,但是控制器中定义了_empty方法,记录_empty方法作为调用
            $call = [$instance, '_empty'];
            $vars = [$action];
        } elseif (is_callable([$instance, '__call'])) {
            // 如果操作方法和_empty方法都不存在,但是控制器中定义了__call方法,记录__call方法作为调用
            $call = [$instance, '__call'];
            $vars = [$action];
        } else {
            // 如果都无法调用,抛出HTTP异常,表示操作方法不存在
            throw new HttpException(404, lang('addon action %s not found', [get_class($instance) . '->' . $action . '()']));
        }
        // 触发addons_action_begin事件,可以在事件处理程序中对操作的执行进行干预
        Event::trigger('addons_action_begin', $call);
        // 调用记录的操作方法,并返回执行结果
        return call_user_func_array($call, $vars);
    }

    /**
     * 伪静态规范化重定向（SEO）
     *
     * 插件配置了伪静态规则（config.php 的 rewrite，保存配置/启用时同步进 config/addons.php 的 route）后，
     * 同一内容会存在「原生插件路由 /addons/...」与「伪静态地址」两份可访问地址。
     * 经原生地址访问时 301 重定向到伪静态地址，让搜索引擎只收录规范地址，避免重复内容分散权重；
     * 未配置伪静态、规则未命中或尚未同步到 route 的插件不受影响，行为与升级前完全一致。
     *
     * 安全性说明：
     * - 仅处理 GET/HEAD 请求，POST 等写操作不跳转（重定向会丢失请求体与请求语义）；
     * - 目标地址由 addon_rewrite_url() 生成，与插件内部 addon_url() 的输出完全一致，
     *   且仅在规则已真实注册到框架路由表时采用，不会跳转到打不开的地址；
     * - 目标路径与当前路径一致时跳过，杜绝循环重定向。
     *
     * @param Request $request    当前请求对象
     * @param string  $addon      插件名
     * @param string  $controller 控制器名
     * @param string  $action     操作名
     * @return void
     * @throws HttpResponseException 需要 301 时抛出，由框架输出重定向响应
     */
    protected static function redirectToRewrite(Request $request, string $addon, string $controller, string $action): void
    {
        if (!$request->isGet() && !$request->isHead()) {
            return;
        }
        // 仅处理原生插件路由访问（/addons/...）；伪静态地址直接访问时不做跳转
        $pathinfo = $request->pathinfo();
        if (0 !== strpos($pathinfo, 'addons/')) {
            return;
        }
        // 业务参数 = 路由段解析参数（剔除路由系统键）+ GET 查询串；
        // 取值链路与插件内部 addon_url() 完全一致，保证重定向目标与站内链接相同
        $params = $request->route();
        unset($params['addon'], $params['controller'], $params['action']);
        $params = array_merge($request->get(), $params);
        // 未配置伪静态规则或全部规则不可用时返回 null，回退原生地址正常访问
        $canonical = addon_rewrite_url($addon, $controller, $action, $params);
        if (null === $canonical) {
            return;
        }
        // 目标与当前路径一致时不跳转（规则本身以 addons/ 开头的极端情况）
        if (trim((string) parse_url($canonical, PHP_URL_PATH), '/') === trim($pathinfo, '/')) {
            return;
        }

        throw new HttpResponseException(Response::create($canonical, 'redirect')->code(301));
    }
}
