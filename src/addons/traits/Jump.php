<?php

declare(strict_types=1);

namespace think\addons\traits;

use think\exception\HttpResponseException;
use think\Response;

/**
 * 控制器跳转与响应公共能力
 *
 * 供插件基础框架的控制器基类（think\addons\Controller）复用，
 * 行为与宿主应用 app\BaseController 保持一致：
 * - 返回结构统一为 code/msg/data/url/wait
 * - HTML 请求渲染跳转模板（优先宿主项目 config/jump.php 的 dispatch_success_tmpl /
 *   dispatch_error_tmpl；未接入该配置的项目回退本包内置模板）
 * - Ajax/JSON 请求直接输出 json，error 时自动注入 token
 *
 * 使用前提：宿主类须提供 $app（应用实例）与 $request（请求实例）两个属性，
 * think\addons\Controller 与 app\BaseController 均已满足。
 *
 * @property \think\App     $app     应用实例（由使用该 trait 的控制器提供）
 * @property \think\Request $request 请求实例（由使用该 trait 的控制器提供）
 */
trait Jump
{
    /**
     * 操作成功跳转
     *
     * @param mixed       $msg    提示信息
     * @param string|null $url    跳转的URL地址
     * @param mixed       $data   返回的数据
     * @param int         $wait   跳转等待时间
     * @param array       $header 发送的Header信息
     * @return void
     */
    protected function success($msg = '', ?string $url = null, $data = '', int $wait = 3, array $header = [])
    {
        if (is_null($url) && isset($_SERVER['HTTP_REFERER'])) {
            $url = $_SERVER['HTTP_REFERER'];
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : (string)$this->app->route->buildUrl($url);
        }

        $result = [
            'code' => 1,
            'msg'  => $msg,
            'data' => $data,
            'url'  => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        // 把跳转模板的渲染下沉，这样在 response_send 行为里通过 getData() 获得的数据格式是一致的
        if ('html' == strtolower($type)) {
            $type     = 'view';
            $response = Response::create($this->getJumpTemplate('success'), $type)->assign($result)->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }

        throw new HttpResponseException($response);
    }

    /**
     * 操作错误跳转
     *
     * @param mixed       $msg    提示信息
     * @param string|null $url    跳转的URL地址
     * @param mixed       $data   返回的数据
     * @param int         $wait   跳转等待时间
     * @param array       $header 发送的Header信息
     * @param int         $code   返回的业务状态码
     * @return void
     */
    protected function error($msg = '', ?string $url = null, $data = [], int $wait = 3, array $header = [], $code = 0)
    {
        if (is_null($url)) {
            $url = $this->request->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : (string)$this->app->route->buildUrl($url);
        }
        if ($this->request->isAjax() && is_array($data)) {
            $data['token'] = token();
        }
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
            'url'  => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        if ('html' == strtolower($type)) {
            $type     = 'view';
            $response = Response::create($this->getJumpTemplate('error'), $type)->assign($result)->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }

        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的API数据到客户端
     *
     * @param mixed  $data   要返回的数据
     * @param int    $code   返回的code
     * @param mixed  $msg    提示信息
     * @param string $type   返回数据格式
     * @param array  $header 发送的Header信息
     * @return void
     */
    protected function result($data, $code = 0, $msg = '', $type = '', array $header = [])
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => time(),
            'data' => $data,
        ];

        $type     = $type ?: $this->getResponseType();
        $response = Response::create($result, $type)->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * URL重定向
     *
     * @param string $url  跳转的URL表达式
     * @param int    $code http code
     * @param array  $with 隐式传参
     * @return void
     */
    protected function redirect($url, $code = 302, $with = [])
    {
        $response = Response::create($url, 'redirect');

        $response->code($code)->with($with);

        throw new HttpResponseException($response);
    }

    /**
     * 获取当前的response 输出类型
     *
     * @return string
     */
    protected function getResponseType()
    {
        return $this->request->isJson() || $this->request->isAjax() ? 'json' : 'html';
    }

    /**
     * 获取跳转提示模板路径
     *
     * 优先宿主项目 config/jump.php 中配置的模板；未接入该配置的项目
     * （如独立使用本扩展包）回退到本包内置模板，保证跳转页可用。
     *
     * @param string $type success|error
     * @return string 模板文件绝对路径
     */
    protected function getJumpTemplate(string $type): string
    {
        $key  = 'success' === $type ? 'dispatch_success_tmpl' : 'dispatch_error_tmpl';
        $tmpl = $this->app->config->get('jump.' . $key);
        if (empty($tmpl)) {
            $tmpl = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'dispatch_jump.tpl';
        }
        return $tmpl;
    }
}
