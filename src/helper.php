<?php

declare(strict_types=1);

use think\facade\Event;

use think\facade\Cache;
use think\helper\Str;
use think\FileHelper;
define('DS', DIRECTORY_SEPARATOR);



// 插件类库自动载入
spl_autoload_register(function ($class) {
    // 获取类名
    $class = ltrim($class, '\\');
    // 获取根目录
    $root_path = str_replace('\\', '/', dirname(__DIR__));
    $dir = strstr($root_path, 'vendor', true);
    // 获取命名空间
    $namespace = 'addons';
    if (strpos($class, $namespace) === 0) {
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $namespace . $path;
        if (file_exists($dir)) {
            include $dir;
            return true;
        }
        return false;
    }
    return false;
});

if (!function_exists('hook')) {
    /**
     * 执行插件钩子
     * 
     * 通过调用此函数,可以触发一个插件钩子,允许插件在特定的事件点插入自定义代码
     * 这是插件系统的核心功能之一,它使得主题和插件可以无侵入地扩展和修改应用程序的行为
     * 
     * @param string $event 钩子的名称,标识要触发的事件
     * @param array|null $params 传递给钩子函数的参数,可以是单个参数或参数数组
     * @param bool $once 指定钩子是否只执行一次.如果设置为true,则在第一次触发后取消订阅
     * @return mixed 返回钩子执行的结果,通常是字符串拼接的结果,也可以是其他数据类型
     */
    function hook($event, $params = null, bool $once = false)
    {
        // 触发事件,调用所有订阅了此事件的钩子函数,并根据$once参数决定是否只执行一次
        $result = Event::trigger($event, $params, $once);
        // 将所有钩子函数的返回值拼接成一个字符串并返回
        // 这样做是为了方便处理多个钩子函数返回的结果,尤其是当它们都是字符串时
        return join('', $result);
    }
}



if (!function_exists('get_addons_info')) {
    /**
     * 获取插件的基本信息
     * 
     * 本函数用于通过插件名获取特定插件的基础信息
     * 如果插件不存在或无法实例化,则返回空数组;否则,返回插件实例的info方法返回的信息
     * 
     * @param string $name 插件的名称.用于唯一标识一个插件
     * @return mixed|array 如果插件存在并成功实例化,返回插件的信息数组;否则,返回空数组
     */
    function get_addons_info($name)
    {
        // 实例化指定名称的插件
        $addon = get_addons_instance($name);
        // 检查插件是否成功实例化,如果没有成功,返回空数组
        if (!$addon) {
            return [];
        }
        // 返回插件实例的信息数组
        return $addon->getInfo();
    }
}

if (!function_exists('set_addons_info')) {
    /**
     * 设置插件的配置信息
     * 本函数用于更新插件的配置信息,通过提供插件名称和一个新的配置数组来更新插件的信息
     * 如果插件不存在或无法实例化,则函数不会进行更新操作并返回空数组
     * 如果插件存在并成功更新信息,则返回插件实例的更新结果
     * 
     * @param string $name 插件的名称.如果未提供名称,则默认为空字符串
     * @param array $array 一个包含插件新配置信息的数组.如果未提供数组,则默认为空数组
     * @return mixed|bool 如果插件不存在或无法实例化,返回空数组;如果成功更新插件信息,返回插件实例的更新结果
     */
    function set_addons_info($name = '', $array = [])
    {
        // 实例化指定名称的插件
        $addon = get_addons_instance($name);
        // 检查插件是否成功实例化
        if (!$addon) {
            return [];
        }
        // 调用插件实例的方法来更新插件信息
        return $addon->setInfo($name, $array);
    }
}

if (!function_exists('get_addons_config')) {
    /**
     * 获取插件的配置信息
     * 
     * 本函数用于检索指定插件的配置信息
     * 配置来源分两层：插件目录 config.php 提供默认配置结构，addon_config 数据表存储用户修改后的配置值
     * 数据库中的配置值优先生效（覆盖文件默认值），卸载插件时可按 addon 字段统一清理
     * addon_config 表尚未创建时自动降级为仅读取文件配置，保持向后兼容
     * 
     * @param string $name 插件的名称.这是识别插件的唯一标识符
     * @param bool $type 默认为false,返回完整配置结构（含type/value等字段定义，与历史行为一致）
     *                  设置为true时,返回简化的 配置键=>配置值 映射,方便插件业务代码直接使用
     * @return mixed|array 如果插件存在并成功获取配置,则返回配置信息；如果插件不存在或获取配置失败,则返回一个空数组
     */
    function get_addons_config($name, $type = false)
    {
        // 获取指定插件的实例
        $addon = get_addons_instance($name);
        // 检查插件实例是否获取成功
        if (!$addon) {
            return [];
        }
        // 基础配置：插件目录 config.php（保持原有行为）
        $config = $addon->getConfig();
        if (!is_array($config)) {
            $config = [];
        }
        // 数据库覆盖：addon_config 表中该插件的配置值优先生效
        // 仅覆盖 config.php 已定义的配置项；数据库残留的孤儿字段（配置项已被删除/改名）不混入配置结构，
        // 否则会以纯字符串元素进入配置数组，导致配置管理页渲染崩溃（Cannot access offset of type string on string）
        $dbConfig = get_addons_db_config($name);
        foreach ($dbConfig as $field => $value) {
            if (isset($config[$field]) && is_array($config[$field]) && array_key_exists('value', $config[$field])) {
                $config[$field]['value'] = $value;
            }
        }
        // $type=true 时返回简化的 键=>值 映射
        if ($type) {
            $values = [];
            foreach ($config as $field => $item) {
                $values[$field] = (is_array($item) && array_key_exists('value', $item)) ? $item['value'] : $item;
            }
            return $values;
        }
        return $config;
    }
}

if (!function_exists('get_addons_db_config')) {
    /**
     * 读取 addon_config 数据表中指定插件的配置值（带缓存）
     * 
     * 缓存键为 addon_config_{插件名}，写入配置时自动清除
     * addon_config 表尚未创建时返回空数组（不缓存，便于建表后立即生效）
     * 
     * @param string $name 插件的名称
     * @return array 配置键=>配置值 映射（值已 JSON 反序列化）
     */
    function get_addons_db_config($name)
    {
        $cacheKey = 'addon_config_' . $name;
        $config   = Cache::get($cacheKey);
        if (!is_array($config)) {
            try {
                $rows = \think\facade\Db::name('addon_config')
                    ->where('addon', $name)
                    ->column('value', 'field');
            } catch (\Throwable $e) {
                // addon_config 表尚未创建时兜底，降级为无数据库配置
                return [];
            }
            $config = [];
            foreach ($rows as $k => $v) {
                $decoded    = json_decode((string)$v, true);
                $config[$k] = ($decoded === null && $v !== 'null') ? $v : $decoded;
            }
            Cache::set($cacheKey, $config);
        }
        return $config;
    }
}

if (!function_exists('set_addons_config')) {
    /**
     * 设置插件的配置信息
     * 本函数用于更新指定插件的配置
     * 写入分两层：插件目录 config.php 保存完整配置结构（保持原有行为），
     * 同时将各配置项的 value 同步到 addon_config 数据表（独立存储，卸载时可统一清理）
     * 写入后自动清除 addon_config_{插件名} 缓存
     * addon_config 表尚未创建时自动降级为仅写文件，保持向后兼容
     * @param string $name 插件名称.如果未指定名称,则默认为空字符串
     * @param array $array 新的配置信息数组.如果未指定配置数组,则默认为空数组
     * @return mixed|bool 如果插件不存在,则返回空数组.如果插件存在且配置更新成功,则返回true.否则,返回false
     */
    function set_addons_config($name = '', $array = [])
    {
        // 获取指定插件的实例
        $addon = get_addons_instance($name);
        // 检查插件实例是否存在,如果不存在,则返回空数组
        if (!$addon) {
            return [];
        }
        // 调用插件实例的setConfig方法来更新插件的配置文件（保持原有行为）
        $result = $addon->setConfig($name, $array);
        // 同步配置值到 addon_config 数据表
        try {
            $time = time();
            foreach ($array as $field => $item) {
                $value = (is_array($item) && array_key_exists('value', $item)) ? $item['value'] : $item;
                $data  = [
                    'value'       => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'update_time' => $time,
                ];
                $id = \think\facade\Db::name('addon_config')
                    ->where('addon', $name)
                    ->where('field', (string)$field)
                    ->value('id');
                if ($id) {
                    \think\facade\Db::name('addon_config')->where('id', $id)->update($data);
                } else {
                    $data['addon']       = $name;
                    $data['field']       = (string)$field;
                    $data['create_time'] = $time;
                    \think\facade\Db::name('addon_config')->insert($data);
                }
            }
            Cache::delete('addon_config_' . $name);
        } catch (\Throwable $e) {
            // addon_config 表尚未创建时忽略，仅保留文件存储
        }
        return $result;
    }
}

if (!function_exists('get_addons_config_value')) {
    /**
     * 读取插件单个配置项的值（便捷函数，供插件业务代码使用）
     * 
     * 优先返回 addon_config 数据表中的值，其次回退到 config.php 中的默认值
     * 
     * @param string $name 插件的名称
     * @param string $field 配置键
     * @param mixed $default 配置不存在时的默认值
     * @return mixed 配置值
     */
    function get_addons_config_value($name, $field, $default = null)
    {
        $values = get_addons_config($name, true);
        return (is_array($values) && array_key_exists($field, $values)) ? $values[$field] : $default;
    }
}

if (!function_exists('set_addons_config_value')) {
    /**
     * 写入插件单个配置项的值（便捷函数，供插件业务代码使用）
     * 
     * 仅写入 addon_config 数据表，不修改插件目录的 config.php 文件；写入后自动清除缓存
     * 
     * @param string $name 插件的名称
     * @param string $field 配置键
     * @param mixed $value 配置值（自动 JSON 序列化）
     * @return bool 写入成功返回 true，addon_config 表不存在时返回 false
     */
    function set_addons_config_value($name, $field, $value)
    {
        try {
            $time = time();
            $data = [
                'value'       => json_encode($value, JSON_UNESCAPED_UNICODE),
                'update_time' => $time,
            ];
            $id = \think\facade\Db::name('addon_config')
                ->where('addon', $name)
                ->where('field', $field)
                ->value('id');
            if ($id) {
                \think\facade\Db::name('addon_config')->where('id', $id)->update($data);
            } else {
                $data['addon']       = $name;
                $data['field']       = $field;
                $data['create_time'] = $time;
                \think\facade\Db::name('addon_config')->insert($data);
            }
            Cache::delete('addon_config_' . $name);
            return true;
        } catch (\Throwable $e) {
            // addon_config 表尚未创建时返回 false
            return false;
        }
    }
}

if (!function_exists('get_addons_instance')) {
    /**
     * 获取插件的单例对象
     * 
     * 本函数用于获取一个插件的单例实例
     * 如果插件已实例化,则直接返回已存在的实例;否则,尝试实例化插件类,并返回新的实例
     * 插件的实例化只会在第一次调用时发生,之后的调用都会返回相同的实例,实现了单例模式
     * 
     * @param string $name 插件的名称.这是用于唯一标识插件的字符串
     * @return mixed|null 返回插件的实例对象,如果插件不存在或无法实例化,则返回null
     */
    function get_addons_instance($name)
    {
        // 使用静态变量存储已实例化的插件,避免重复实例化
        static $_addons = [];
        // 检查是否已存在该插件的实例,如果存在直接返回
        if (isset($_addons[$name])) {
            return $_addons[$name];
        }
        // 通过插件名获取插件的类名
        $class = get_addons_class($name);
        // 检查插件类是否存在,如果存在则实例化插件类
        if (class_exists($class)) {
            // 实例化插件类,并传入应用实例作为构造函数的参数
            $_addons[$name] = new $class(app());
            return $_addons[$name];
        } else {
            // 如果插件类不存在,返回null
            return null;
        }
    }
}

if (!function_exists('get_addons_class')) {
    /**
     * 根据插件名和类型获取插件类的完整类名
     * 
     * 该函数用于生成并返回指定插件的类名,根据插件名、类型和可选的类名片段
     * 主要用于在不同的插件管理和调用场景中,动态生成插件类的完全限定名
     * 
     * @param string $name 插件的名称.这是用于唯一标识插件的字符串
     * @param string $type 类的类型.用于确定生成类名的命名空间.默认为'hook'
     * @param string $class 可选的类名片段.当需要指定插件中的特定类时使用,可以是类的路径片段
     * @return mixed|string 返回插件类的完全限定名,如果类不存在则返回空字符串
     */
    function get_addons_class($name, $type = 'hook', $class = null)
    {
        // 移除$name中的前后空格
        $name = trim($name);
        // 当$class提供并且包含点号时,处理为命名空间的数组形式
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);
            // 将数组中的最后一个元素转换为StudlyCaps格式,用于类名
            $class[count($class) - 1] = Str::studly(end($class));
            // 通过逆向操作将数组转换回字符串形式的命名空间
            $class = implode('\\', $class);
        } else {
            // 如果没有提供$class或者$class为null,将$name或$class转换为StudlyCaps格式,用于类名
            $class = Str::studly(is_null($class) ? $name : $class);
        }
        // 根据$type生成插件类的命名空间
        switch ($type) {
                // 如果$type为'controller',则生成控制器的命名空间
            case 'controller':
                $namespace = '\\addons\\' . $name . '\\controller\\' . $class;
                // 匹配空控制器
                if (!class_exists($namespace)) {
                    $namespace = '\\addons\\' . $name . '\\Controller\\' . config('route.empty_controller');
                }
                break;
                // 默认情况下,生成插件基类的命名空间
            default:
                $namespace = '\\addons\\' . $name . '\\Plugin';
        }
        // 检查命名空间对应的类是否存在,如果存在则返回命名空间字符串,否则返回空字符串
        return class_exists($namespace) ? $namespace : '';
    }
}


if (!function_exists('addon_rewrite_usable')) {
    /**
     * 判断参数值能否作为伪静态规则的路径段占位符
     *
     * 判据与 think\Route 的 default_route_pattern 保持一致（默认 [\w\.]+），
     * 即只允许字母、数字、下划线、点；含逗号、斜杠、问号、井号或其它字符的值
     * 无法被路由规则捕获，这类参数须留在查询串中传递。
     *
     * @param mixed $value 参数值
     * @return bool
     */
    function addon_rewrite_usable($value)
    {
        if ($value === null || $value === '' || is_array($value)) {
            return false;
        }
        return preg_match('/^[\w\.]+$/', (string)$value) === 1;
    }
}

if (!function_exists('addon_rewrite_fill')) {
    /**
     * 用业务参数填充单条伪静态规则模板
     *
     * 支持 ThinkPHP 原生占位符写法：<name> 为必填段、[<name>] 为可选段。
     * 任一必填占位符缺参或取值非法时整条规则不可用，返回 null 由调用方换用其它规则。
     *
     * @param string $rule   规则模板，如 /res/<chaxun_id>/<unicode>
     * @param array  $params 业务参数
     * @return string|null 填充后的路径（含查询串）；该规则不可用返回 null
     */
    function addon_rewrite_fill($rule, $params)
    {
        $used = [];
        $fail = false;

        // 可选段：参数缺失时整段移除
        $rule = preg_replace_callback('/\[\s*<([A-Za-z_][A-Za-z0-9_]*)>\s*\]/', function ($m) use ($params, &$used) {
            $name = $m[1];
            if (!addon_rewrite_usable($params[$name] ?? null)) {
                return '';
            }
            $used[$name] = $params[$name];
            return '{' . $name . '}';
        }, $rule);

        // 必填段：缺参或值非法则整条规则不可用
        $rule = preg_replace_callback('/<([A-Za-z_][A-Za-z0-9_]*)>/', function ($m) use ($params, &$used, &$fail) {
            $name = $m[1];
            if (!addon_rewrite_usable($params[$name] ?? null)) {
                $fail = true;
                return $m[0];
            }
            $used[$name] = $params[$name];
            return '{' . $name . '}';
        }, $rule);

        if ($fail) {
            return null;
        }

        // 用实际值替换占位（不编码，与原生路由的参数还原规则保持一致）
        $path = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', function ($m) use ($used) {
            return (string)$used[$m[1]];
        }, $rule);

        $path = preg_replace('#/+#', '/', '/' . trim((string)$path, '/'));

        // 未被规则占位符吃掉的参数，以查询串追加（逗号还原以保持 A,B 观感）
        $query = [];
        foreach (array_diff_key($params, $used) as $k => $v) {
            if (is_array($v) || $v === null || $v === '') {
                continue;
            }
            $query[] = rawurlencode((string)$k) . '=' . str_replace('%2C', ',', rawurlencode((string)$v));
        }
        if (!empty($query)) {
            $path .= (strpos($path, '?') === false ? '?' : '&') . implode('&', $query);
        }

        return $path;
    }
}

if (!function_exists('addon_rewrite_registered')) {
    /**
     * 校验伪静态规则是否已同步进框架路由表（config/addons.php 的 route）
     *
     * 插件的 rewrite 会在保存配置/启用时由框架同步写入 route，运行时的路由注册
     * 读取的正是 route。两处不一致时按规则生成的地址会打不开：可能是尚未同步，
     * 也可能是该路径已被其它插件抢占（多个插件都映射同一路径时只有一条生效）。
     * 故生成前须回查 route 确认「该规则模板 + 该目标」确实已注册，否则回退原生路由。
     *
     * 非框架环境（如独立单元测试）下 config() 不存在，跳过校验。
     *
     * @param string $rule   规则模板（键），如 /res/<chaxun_id>/<unicode>
     * @param string $target 目标地址，如 dchaxun/index/res
     * @return bool
     */
    function addon_rewrite_registered($rule, $target)
    {
        if (!function_exists('config')) {
            return true;
        }

        $route = config('addons.route');
        if (!is_array($route) || empty($route)) {
            return false;
        }

        foreach ($route as $key => $val) {
            if (is_array($val)) {
                $val = implode('/', array_filter([
                    $val['addon'] ?? '',
                    $val['controller'] ?? '',
                    $val['action'] ?? '',
                ], 'strlen'));
            }
            if ((string)$key === (string)$rule && trim((string)$val, '/$') === $target) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('addon_rewrite_url')) {
    /**
     * 按插件自身的伪静态规则（config.php 的 rewrite）生成地址
     *
     * 规则来源与后台插件配置页同源，均为 get_addons_config($addon)['rewrite']：
     *   键 = URL 规则模板（ThinkPHP 路由语法，支持 <name> / [<name>] 占位符）
     *   值 = 目标地址 '插件/控制器/操作'
     * 例：'/res/<chaxun_id>/<unicode>' => 'dchaxun/index/res'
     *
     * 同一「插件/控制器/操作」可能配置多条规则（如带/不带 unicode 段），
     * 此处按「占位符越多越具体」排序后依次尝试，命中即返回；全部失败返回 null，
     * 由调用方回退到原生插件路由。
     *
     * @param string $addon      插件名
     * @param string $controller 控制器名
     * @param string $action     操作名
     * @param array  $params     业务参数
     * @return string|null 命中并填充成功的路径；未配置或未命中返回 null
     */
    function addon_rewrite_url($addon, $controller, $action, $params = [])
    {
        // 每个插件每请求只读取一次伪静态配置（get_addons_config 涉及文件与
        // addon_config 表读取，而本函数会被模板高频调用）
        static $rewriteCache = [];
        if (!array_key_exists($addon, $rewriteCache)) {
            $config = get_addons_config($addon, true);
            $rewriteCache[$addon] = (is_array($config) && isset($config['rewrite']) && is_array($config['rewrite']))
                ? $config['rewrite']
                : [];
        }
        $rewrite = $rewriteCache[$addon];
        if (empty($rewrite)) {
            return null;
        }

        $target = $addon . '/' . $controller . '/' . $action;
        $rules  = [];

        foreach ($rewrite as $key => $val) {
            if (is_array($val)) {
                // 兼容 {addon,controller,action} 结构
                $val = implode('/', array_filter([
                    $val['addon'] ?? '',
                    $val['controller'] ?? '',
                    $val['action'] ?? '',
                ], 'strlen'));
            }
            // 值只保留 '插件/控制器/操作'，容忍首尾 / 与 fastadmin 风格的结尾 $
            if (trim((string)$val, '/$') === $target) {
                $key     = (string)$key;
                $rules[] = ['rule' => $key, 'score' => (int)preg_match_all('/<[A-Za-z_][A-Za-z0-9_]*>/', $key)];
            }
        }

        if (empty($rules)) {
            return null;
        }

        // 占位符越多越具体，优先尝试；同分保持配置顺序（PHP 8 起 usort 稳定）
        usort($rules, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        foreach ($rules as $item) {
            $path = addon_rewrite_fill($item['rule'], $params);
            if ($path === null) {
                continue;
            }
            // 规则须已真实注册到框架路由表，否则生成出的地址不可访问
            if (!addon_rewrite_registered($item['rule'], $target)) {
                continue;
            }
            return $path;
        }

        return null;
    }
}

if (!function_exists('addon_url')) {
    /**
     * 生成插件访问地址
     *
     * 【优先伪静态】若插件在自己的 config.php 里配置了 rewrite 伪静态规则，
     * 则先按「插件/控制器/操作」反查规则并填充占位符，命中时直接输出伪静态地址
     * （业务参数能进路径段的并入路径段，其余以查询串追加）；未配置或未命中规则时，
     * 回退到下面的原生路由拼接，输出与未启用伪静态时完全一致。
     *
     * 原生路由规则为 /addons/{插件}/{控制器}/{方法}，附加参数以 键/值 形式逐段追加。
     *
     * 【关键】插件路由的参数还原逻辑在 think\route\Rule::parseUrlParams()：
     *     preg_replace_callback('/(\w+)\/([^\/]+)/', ...)
     * 它按「键/值」逐段取参，且不做任何 URL 解码。因此：
     *   1. 参数值不可 rawurlencode —— 否则 'A,B' 会变成 'A%2CB'，控制器
     *      $this->request->param('unicode') 拿到的就是 'A%2CB'，无法再按逗号拆分；
     *   2. 参数值中不可含 "/"，否则会被切成新的键值对；含 "/"、"?"、"#" 的参数
     *      改走查询串传递，Request::param() 同样会合并 GET 参数，读取方式不变；
     *   3. 空值段无法被上述正则匹配，直接跳过（控制器取到自身默认值）。
     *
     * @param string $url    形如 dchaxun/index/chaxun；仅传插件名时返回插件目录
     * @param array  $params 附加参数
     * @param bool   $domain 是否返回带域名的完整地址
     * @param bool   $suffix 保留参数（兼容调用签名；当前不追加 url_html_suffix）
     * @return string
     */
    function addon_url($url, $params = [], $domain = false, $suffix = false)
    {
        $segments = array_values(array_filter(explode('/', trim((string)$url, '/')), 'strlen'));

        if (empty($segments)) {
            return '';
        }

        $addon = $segments[0];

        // 仅传插件名时返回插件目录，便于拼接插件内的文件路径
        // （如 ROOT_PATH . addon_url('source', false, false) . '/vendor/...'）。
        if (count($segments) === 1) {
            $path = '/addons/' . $addon;
        } else {
            $controller = $segments[1] ?? 'index';
            $action     = $segments[2] ?? 'index';

            // 先尝试按插件伪静态规则生成；未命中（返回 null）时回退原生路由
            $path = addon_rewrite_url($addon, $controller, $action, is_array($params) ? $params : []);

            if ($path === null) {
                $path  = '/addons/' . $addon . '/' . $controller . '/' . $action;
                $query = [];

                if (is_array($params)) {
                    foreach ($params as $k => $v) {
                        if (is_array($v) || $v === null || $v === '') {
                            continue;
                        }
                        $v = (string)$v;
                        if (strpbrk($v, '/?#') === false) {
                            $path .= '/' . $k . '/' . $v;
                        } else {
                            $query[$k] = $v;
                        }
                    }
                }

                if (!empty($query)) {
                    $path .= '?' . http_build_query($query);
                }
            }
        }

        if ($domain) {
            $path = app()->request->domain() . $path;
        }

        return $path;
    }
}

if (!function_exists('get_addons_menu')) {
    /**
     * 获取插件菜单
     * @param string $name
     * @return mixed|array
     */
    function get_addons_menu($name)
    {
        $menu = app()->getRootPath() . 'addons' . DS . $name . DS . 'menu.php';
        if (file_exists($menu)) {
            return include_once $menu;
        }
        return [];
    }
}

if (!function_exists('get_addons_list')) {
    /**
     * 获得插件列表
     * @return mixed|array
     */
    function get_addons_list()
    {
        $list = Cache::get('addons_list');
        if (empty($list)) {
            // 插件目录
            $addonsPath = app()->getRootPath() . 'addons' . DS;
            $results = FileHelper::getFolder($addonsPath);
            $list = [];
            foreach ($results as $k => $v) {
                if ($v['type'] == 'dir') {
                    // 插件文件名
                    $pluginName = join(DS, [$v['path_name'], 'Plugin.php']);
                    if (!is_file($pluginName)) {
                        continue;
                    }
                    // 插件信息
                    $infoFile = join(DS, [$v['path_name'], 'info.json']);
                    if (!is_file($infoFile)) {
                        continue;
                    }

                    $info = json_decode(FileHelper::readFile($infoFile), true);
                    //                    如果存在则说明需要设置
                    $infoFile = join(DS, [$v['path_name'], 'config.php']);
                    if (is_file($infoFile)) {
                        $info['is_set']=1;
                    }else{
                        $info['is_set']=0;
                    }
                    if (!isset($info['name'])) {
                        continue;
                    }
                    $list[$v['name']] = $info;
                }
            }
            Cache::set('addons_list', json_encode($list));
        } else {
            $list = json_decode($list, true);
        }
        return $list;
    }
}
