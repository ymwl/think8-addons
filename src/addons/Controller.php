<?php

declare(strict_types=1);

namespace think\addons;

use think\App;
use think\addons\traits\Jump;
use think\facade\View;

class Controller
{
    use Jump;

    /**
     * @var mixed|Model
     */
    protected $model = null;

    /**
     * 无需登录及鉴权的方法
     * @var mixed|array
     */
    protected $noNeedLogin = [];

    /**
     * 需要登录无需鉴权的方法
     * @var mixed|array
     */
    protected $noNeedAuth = [];

    // app 容器
    protected $app;
    // 请求对象
    protected $request;
    // 当前插件标识
    protected $name;
    // 插件路径
    protected $addon_path;
    // 视图模型
    protected $view;
    // 插件配置
    protected $addon_config = '';
    // 插件信息
    protected $addon_info = '';

    /**
     * 插件构造函数
     * Addons constructor.
     * @param \think\App $app
     */
    public function __construct(App $app = null)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->name = $this->getName();
        $this->addon_path = $app->addons->getAddonsPath() . $this->name . DIRECTORY_SEPARATOR;
        $this->addon_config = "addon_{$this->name}_config";
        $this->addon_info = "addon_{$this->name}_info";
        $this->view = View::engine('Think');
        $this->view->config([
            'view_path' => $this->addon_path . 'view' . DIRECTORY_SEPARATOR
        ]);
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
        // 初始化操作可以在这里进行
    }

    /**
     * 获取插件标识
     * @return mixed|null
     */
    final protected function getName()
    {
        $class = get_class($this);
        [, $name,] = explode('\\', $class);
        $this->request->addon = $name;
        return $name;
    }

    /**
     * 加载模板输出
     * @param string $template
     * @param array $vars 模板文件名
     * @return false|mixed|string 模板输出变量
     * @throws \think\Exception
     */
    protected function fetch($template = '', $vars = [])
    {
        return $this->view->fetch($template, $vars);
    }

    /**
     * 渲染内容输出
     *
     * 后两个参数仅为兼容源项目（FastAdmin / ThinkPHP5 风格）的 4 参数调用形式，
     * 传入时忽略；PHP 本身会忽略用户函数的多余实参，此处显式声明以明确兼容意图。
     *
     * @param string $content 模板内容
     * @param array  $vars    模板输出变量
     * @param array  $config  保留参数（忽略）
     * @param array  $options 保留参数（忽略）
     * @return mixed
     */
    protected function display($content = '', $vars = [], $config = [], $options = [])
    {
        return $this->view->display($content, $vars);
    }

    /**
     * 模板变量赋值
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return $this
     */
    protected function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->view->assign($name);
        } else {
            $this->view->assign([$name => $value]);
        }
        return $this;
    }

    /**
     * 初始化模板引擎
     * @param array|string $engine 引擎参数
     * @return $this
     */
    protected function engine($engine)
    {
        $this->view->engine($engine);
        return $this;
    }
}
