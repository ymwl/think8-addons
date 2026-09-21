### ThinkPHP 8.0.0+ Addons Package

当前版本：`v1.1.5`

#### 更新日志

**v1.1.5**（2026-09-22）
- 修正 README 的 `rewrite` 配置示例结构：原示例为列表 + `name` 字段（`[['name' => 'rewrite', ...]]`），而 `get_addons_config()` 读取的是关联结构（`$config['rewrite']['value']`），照抄会导致插件取不到 `rewrite`、伪静态不生效；现改为与真实配置一致的 `'rewrite' => [...]`
- 修正 README 示例中伪静态规则的键值方向：原示例为「键 = 控制器/操作、值 = 路径」，与实现及 `config/addons.php` 的 `route` 方向相反；现改为「键 = URL 规则模板（支持 `<name>` 必填段、`[<name>]` 可选段）、值 = 目标地址（`插件/控制器/操作`）」，并补充规则方向与 `addon_url()` 输出对应关系的说明
- 纯文档修正，`src/` 代码与 v1.1.4 完全一致

**v1.1.4**（2026-09-22）
- **增强 `addon_url()`（无破坏性变更）**：插件在自身 `config.php` 里配置了 `rewrite` 伪静态规则时，`addon_url()` 会按「插件/控制器/操作」反查规则并填充 `<name>` / `[<name>]` 占位符，直接生成伪静态地址；未配置规则、规则未命中、或规则尚未同步到 `config/addons.php` 的 `route` 时，回退原有原生路由拼接，输出与升级前一致
- 参数分配策略：能被规则占位符吃下的参数并入路径段（要求值匹配 `[\w\.]+`，与框架 `default_route_pattern` 一致），其余自动以查询串追加——如含逗号的值（`A,B,C`）不会进入路径段，避免被框架解析成多个键值对
- 新增内部辅助函数 `addon_rewrite_usable()`（参数值是否可用作路径段）、`addon_rewrite_fill()`（填充规则模板）、`addon_rewrite_registered()`（回查路由表）、`addon_rewrite_url()`（按插件 `rewrite` 反查并生成地址，结果按插件名缓存，避免模板高频调用时重复读配置与数据表）
- `addon_rewrite_registered()` 会在生成前回查 `config('addons.route')`，仅当「规则模板 + 目标地址」双匹配时才采用：多个插件映射同一路径时 `route` 表只保留一条生效，若不校验会为落败的插件生成指向其它插件的地址
- 插件不再需要自行维护地址生成类，插件内任意位置统一调用 `addon_url()` 即可，由本包决定输出伪静态还是原生路由

**v1.1.3**（2026-09-22）
- 修复插件前台「插件控制器 XXX 未找到」误报：控制器在构造/初始化期间抛出的 `HttpResponseException`（业务 `error()` / `success()` / `redirect()` 跳转）此前被实例化处的 `catch (\Exception)` 一并捕获并改写成 404；现于 `src/addons/Route.php` 中在该 catch 之前新增 `catch (HttpResponseException $e) { throw $e; }` 予以放行，由框架正常输出业务跳转响应

**v1.1.2**（2026-09-21）
- 新增 `addon_url()`：生成插件访问地址，插件名显式写在第一段（如 `addon_url('test/index/link')`），在后台、其它应用、插件自身模板等任意上下文均可正确生成
- `addon_url()` 参数以「键/值」逐段追加、不做 `rawurlencode`、不追加 URL 后缀；仅传插件名时返回插件目录（`/addons/test`），便于拼接插件内文件路径
- **移除 `addons_url()`（破坏性变更）**：其插件名只取自当前请求上下文（`$request->addon`），在后台/其它应用中会生成 `/addons//index/...` 一类错误地址，且第 3、4 个参数顺序与 `addon_url()` 相反、极易误用；仍在调用该函数的代码请改用 `addon_url()`
- 文档与示例统一改用 `addon_url()`；框架自带插件 `addons/wechat` 的示例模板 `view/info.html` 同步替换

**v1.1.1**（2026-08-26）
- 修复配置项删除后插件配置页渲染崩溃
- 补记：本次发布当时未同步更新本文件的版本号与更新日志，故于 v1.1.2 一并补录

**v1.1.0**（2026-07-31）
- 插件配置统一管理：新增「文件默认值 + `addon_config` 数据表存储」双层配置架构
- 扩展 `get_addons_config()`：数据库配置值自动覆盖文件默认值，新增 `$type=true` 返回简化键值映射
- 增强 `set_addons_config()`：写入 `config.php` 的同时将配置值同步到 `addon_config` 数据表
- 新增 `get_addons_db_config()`：读取数据表配置（带缓存，缓存键 `addon_config_{插件名}`）
- 新增 `get_addons_config_value()` / `set_addons_config_value()`：单个配置项便捷读写
- 完善降级兼容：`addon_config` 表未创建时自动回退纯文件模式，不影响已有插件运行
- 文档：`info.json` 新增版本兼容性声明字段说明（`require_php` / `require_crm` / `max_crm`，由宿主项目在安装/升级/启用时检查）

**v1.0.4**（2025-06-30）
- 多应用安全隔离：插件路由仅在 `index` 应用中注册和执行，防止 admin/install 等管理应用路由污染
- 路由注册前自动检查插件启用状态，已禁用插件的路由将被跳过
- 插件目录扫描增加缓存机制，减少重复 IO 提升性能
- 修复 `loadService()` 中 `json_decode` 错误解析文件路径而非文件内容的 Bug
- 插件路由参数校验提前至事件触发之前，增强安全性
- 移除 `getInfo()` 中自动附加 URL 的冗余逻辑

**v1.0.3**
- 新增 `get_addons_menu()` 获取插件菜单
- 新增 `get_addons_list()` 获取插件列表（含缓存）
- 完善插件安装/卸载生命周期

#### 环境

- php >=8.0.0
- ThinkPHP ^8.0.0（支持多应用模式）

#### 安装
```php
composer require ymwl/think8-addons
```

#### 配置

系统安装后会自动在 `config` 目录中生成 `addons.php` 的配置文件

#### 公共配置

```php
declare(strict_types=1);

return [
    // 是否自动读取取插件钩子配置信息
    'autoload' => false,
    // 当关闭自动获取配置时需要手动配置hooks信息
    'hooks' => [
        // 可以定义多个钩子
        'testhook' => 'test' // 键为钩子名称，用于在业务中自定义钩子处理，值为实现该钩子的插件，
        // 多个插件可以用数组也可以用逗号分割
    ],
    'route' => [],
    'service' => [],
];
```

或者在多应用中 `config` 目录中新建`addons.php`,内容为:

```php
<?php

declare(strict_types=1);

return [
    // 是否自动读取取插件钩子配置信息
    'autoload' => false,
    // 当关闭自动获取配置时需要手动配置hooks信息
    'hooks' => [
        // 可以定义多个钩子
        'testhook' => 'test' // 键为钩子名称，用于在业务中自定义钩子处理，值为实现该钩子的插件，
        // 多个插件可以用数组也可以用逗号分割
    ],
    'route' => [],
    'service' => [],
];

```

#### 创建插件
> 创建的插件可以在view视图中使用，也可以在php业务中使用

安装完成后访问系统时会在项目根目录生成名为`addons`的目录,在该目录中创建需要的插件.

下面写一个例子：

#### 创建`test`插件
> 在`addons`目录中创建`test`目录

#### 创建`钩子`实现类
> 在`test`目录中创建 `Plugin.php` 类文件.注意:类文件首字母需大写

#### 插件`info.json`文件基础信息

```shell
{
    "name": "test",
    "title": "插件测试",
    "description": "ThinkPHP8 插件测试",
    "website": "https://github.com/ymwl/think8-addons",
    "status": "0",
    "is_admin": "0",
    "is_index": "0",
    "install": "1",
    "author": "zx80com@163.com",
    "version": "1.0.0",
    "require_php": "8.0.0",
    "require_crm": "5.0.0",
    "max_crm": "6.0.0"
}
```

**版本兼容性声明字段（可选）**：

| 字段 | 说明 |
|------|------|
| `require_php` | 插件要求的最低 PHP 版本 |
| `require_crm` | 插件要求的最低宿主系统版本（对比宿主项目 `config/version.php` 的 `version`） |
| `max_crm` | 插件兼容的最高宿主系统版本 |

> 三个字段均为可选，未声明时自动跳过对应检查项，老插件无需修改。
> 兼容性检查由宿主项目在插件**安装、升级、启用**时执行（符号象CRM 中为 `app/admin/service/AddonLifecycle.php` 的 `checkCompatibility()` 方法），本扩展包不包含该逻辑。

### 插件`Plugin.php`文件基础信息

```php
<?php
namespace addons\test;	// 注意命名空间规范

use think\Addons;

/**
 * 插件测试
 * @author zx80com@163.com
 */
class Plugin extends Addons	// 需继承think\Addons类
{
    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 实现的testhook钩子方法
     * @return mixed
     */
    public function testhook($param)
    {
        // 调用钩子时候的参数信息
        print_r($param);
        // 当前插件的配置信息，配置信息存在当前目录的config.php文件中，见下方
        print_r($this->getConfig());
        // 可以返回模板，模板文件默认读取的为插件目录中的文件。模板名不能为空！
        return $this->fetch('info');
    }

}
```

#### 创建插件配置文件

> 在test目录中创建`config.php`配置文件，插件配置文件可以省略。

```php
return [
    'rewrite' => [
        'title'  => '伪静态',
        'type'   => 'array',
        'value'  => [
            // 键 = URL 规则模板（ThinkPHP 路由语法，支持 <name> 必填段、[<name>] 可选段）
            // 值 = 目标地址「插件/控制器/操作」
            '/'          => 'test/index/index',
            '/link'      => 'test/index/link',
            '/link/<id>' => 'test/index/link',
        ],
        'rule'   => 'required',
        'msg'    => '',
        'tip'    => '',
        'ok'     => '',
        'extend' => '',
    ],
];
```

> 规则方向为「键 = URL 规则模板、值 = 目标地址（`插件/控制器/操作`）」，与 `config/addons.php` 中同步生成的 `route` 一致。上例中 `addon_url('test/index/link')` 生成 `/link`，`addon_url('test/index/link', ['id' => 1])` 命中 `/link/<id>` 生成 `/link/1`。
>
> 插件只需配好 `rewrite`，其内部（控制器、模板、后台等任意位置）生成地址时统一调用 `addon_url()` 即可：本扩展包会优先按 `rewrite` 输出伪静态地址，未命中时回退原生插件路由，**无需为插件单独编写地址生成类**（v1.1.4+）。
>
> `rewrite` 在保存配置或启用插件时由宿主系统同步进 `config/addons.php` 的 `route`；两者不一致时 `addon_url()` 回退原生路由，不会生成打不开的地址。

#### 创建钩子`模板`文件
> 在test->view目录中创建info.html模板文件，钩子在使用fetch方法时对应的模板文件。

```html
<h1>hello tpl</h1>

如果插件中需要有链接或提交数据的业务，可以在插件中创建controller业务文件，
要访问插件中的controller时使用addon_url生成url链接。
如下：
<a href="{:addon_url('test/index/link')}">link test</a>
或
<a href="{:addon_url('test/index/link', ['id' => 1])}">link test</a>
格式为：
插件名为第一段，第二段为controller中的类名[多级控制器可以用.分割]，第三段为controller中的方法，其余为附加参数
```

#### 创建插件的`controller`文件
> 在test目录中创建controller目录，在controller目录中创建Index.php文件
> controller类的用法与tp6中的controller一致

```php
<?php
namespace addons\test\controller;

class Index
{
    public function link()
    {
        echo 'hello link';
    }
}
```

#### 使用钩子
> 创建好插件后就可以在正常业务中使用该插件中的钩子了
> 使用钩子的时候第二个参数可以省略

#### 模板中使用钩子

```html
<div>{:hook('testhook', ['id'=>1])}</div>
```

#### php业务中使用
> 只要是 ThinkPHP 正常流程中的任意位置均可以使用

```php
hook('testhook', ['id'=>1])
```

#### 插件公共方法
```php
/**
 * 处理插件钩子
 * @param string $event 钩子名称
 * @param array|null $params 传入参数
 * @param bool $once 是否只返回一个结果
 * @return mixed
 */
function hook($event, $params = null, bool $once = false);

/**
 * 读取插件的基础信息
 * @param string $name 插件名
 * @return array
 */
function get_addons_info($name);

/**
 * 获取插件配置信息（文件默认值 + 数据库覆盖，详见下方「插件配置管理」章节）
 * @param string $name 插件名
 * @param bool $type false=返回完整配置结构（默认），true=返回简化的 键=>值 映射
 * @return mixed|array
 */
function get_addons_config($name, $type = false);

/**
 * 设置插件配置信息（写入 config.php 文件并同步到 addon_config 数据表）
 * @param string $name 插件名
 * @param array $array 完整配置结构数组
 * @return mixed|bool
 */
function set_addons_config($name = '', $array = []);

/**
 * 读取 addon_config 数据表中指定插件的配置值（带缓存）
 * @param string $name 插件名
 * @return array 配置键=>配置值 映射
 */
function get_addons_db_config($name);

/**
 * 读取插件单个配置项的值
 * @param string $name 插件名
 * @param string $field 配置键
 * @param mixed $default 配置不存在时的默认值
 * @return mixed
 */
function get_addons_config_value($name, $field, $default = null);

/**
 * 写入插件单个配置项的值（仅写数据表，不修改 config.php）
 * @param string $name 插件名
 * @param string $field 配置键
 * @param mixed $value 配置值（自动 JSON 序列化）
 * @return bool
 */
function set_addons_config_value($name, $field, $value);

/**
 * 获取插件Plugin的单例
 * @param string $name 插件名
 * @return mixed|null
 */
function get_addons_instance($name);

/**
 * 生成插件访问地址（插件名须显式写在第一段）
 *
 * 【优先伪静态】插件若在自身 config.php 中配置了 rewrite 伪静态规则（v1.1.4+），
 * 则先按「插件/控制器/操作」反查规则并填充占位符，命中时直接输出伪静态地址；
 * 未配置规则、规则未命中、或规则未同步到 config/addons.php 的 route 时，
 * 回退原生路由拼接，输出与未启用伪静态时一致。
 *
 * 插件名不取自当前请求上下文（$request->addon），
 * 在后台、其它应用、插件自身模板等任意上下文均可正确生成；
 * 参数以 键/值 逐段追加（不做 rawurlencode），也不追加 URL 后缀。
 *
 * @param string $url 形如 test/index/link；仅传插件名时返回插件目录（/addons/test）
 * @param array $params 附加参数
 * @param bool $domain 是否返回带域名的完整地址
 * @param bool $suffix 保留参数（兼容调用签名；当前不追加 url_html_suffix）
 * @return string
 */
function addon_url($url, $params = [], $domain = false, $suffix = false);

/**
 * 获取插件菜单
 * @param string $name 插件名
 * @return mixed|array
 */
function get_addons_menu($name);

/**
 * 获取插件列表（含缓存）
 * @return mixed|array
 */
function get_addons_list();

```

#### 插件配置管理（v1.1.0+）

> v1.1.0 起，插件配置采用「双层存储」架构：`config.php` 文件提供配置的**结构定义与默认值**，`addon_config` 数据表存储**用户修改后的实际值**，数据库值优先生效。

##### 一、两种存储方式的区别

| 维度 | `config.php` 文件 | `addon_config` 数据表 |
|------|------------------|----------------------|
| 角色 | 配置的结构定义 + 出厂默认值（“表单模板”） | 用户修改后的实际值（“用户数据”） |
| 内容 | 完整结构：`['rewrite'=>['type'=>'text','title'=>'伪静态','value'=>'0']]` | 扁平键值：`addon=test, field=rewrite, value="1"` |
| 来源 | 插件开发者随插件包发布 | 用户在后台配置页保存产生 |
| 生命周期 | 随插件文件分发、升级覆盖、删除 | 随数据库存在，升级不丢失 |
| 读取方式 | `require` PHP 文件（OPcache 加速） | SQL 查询 + 缓存（键 `addon_config_{插件名}`） |

##### 二、为什么需要数据表补充文件配置

1. **升级不丢配置**：`setConfig()` 保存配置时会覆写整个 `config.php`，插件升级时新版本自带的 `config.php` 会覆盖用户修改；配置值存入数据表后，升级只替换代码文件，用户值自动覆盖回来
2. **写入更安全**：避免生产环境插件目录只读导致写文件失败，以及并发写文件损坏、OPcache 旧值等问题
3. **卸载完整清理**：卸载插件时按 `WHERE addon='xxx'` 一次性清理配置，零残留
4. **可查询可审计**：表中含 `create_time`/`update_time`，可跨插件统一查看，随整库备份自动纳入备份策略

##### 三、数据表结构

> 建表脚本参见项目 `update/addon_config_table.sql`（表前缀按实际项目调整）

```sql
CREATE TABLE IF NOT EXISTS `ymwl_addon_config` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `addon` varchar(50) NOT NULL DEFAULT '' COMMENT '插件标识名',
  `field` varchar(100) NOT NULL DEFAULT '' COMMENT '配置键',
  `value` text COMMENT '配置值(JSON)',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_addon_field` (`addon`, `field`),
  KEY `idx_addon` (`addon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件配置表';
```

未创建此表时所有函数自动降级为纯文件模式，不报错、不影响已有插件。

##### 四、get_addons_config() 使用详解

**函数签名**：`get_addons_config($name, $type = false)`

**参数说明**：

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `$name` | string | 无 | 插件名（addons 目录下的目录名） |
| `$type` | bool | `false` | `false`=返回完整配置结构（含 type/title/value 等字段定义，与历史行为一致）；`true`=返回简化的 配置键=>配置值 映射 |

**返回值**：插件不存在返回空数组 `[]`；否则返回合并后的配置数组。

**合并规则**：先读 `config.php` 作为基础，再用 `addon_config` 表中的值覆盖——若文件配置项是含 `value` 键的数组则仅覆盖其 `value`（保留 type/title 等结构），否则直接赋值。

```php
// 默认模式：完整结构（后台配置页渲染表单用）
$config = get_addons_config('test');
// ['rewrite' => ['type'=>'text', 'title'=>'伪静态', 'value'=>'1'], ...]
echo $config['rewrite']['value'];   // 数据库值优先，其次文件默认值

// 简化模式：键值映射（插件业务代码直接取值）
$values = get_addons_config('test', true);
// ['rewrite' => '1', ...]
```

##### 五、set_addons_config() 使用详解

**函数签名**：`set_addons_config($name = '', $array = [])`

**参数说明**：

| 参数 | 类型 | 说明 |
|------|------|------|
| `$name` | string | 插件名 |
| `$array` | array | 完整配置结构数组（与 get_addons_config 默认返回格式一致） |

**返回值**：插件不存在返回空数组 `[]`；写入成功返回写入字节数（`file_put_contents` 结果），失败返回 `false`。

**写入行为**：
1. 将完整结构写入插件目录 `config.php`（保持原有行为）
2. 提取各配置项的 `value`（无 value 键则取项本身），JSON 序列化后 upsert 到 `addon_config` 表
3. 自动清除 `addon_config_{插件名}` 缓存

```php
// 典型场景：后台插件配置页保存
$config = get_addons_config('test');          // 取完整结构
$config['rewrite']['value'] = '1';            // 修改值
set_addons_config('test', $config);           // 双写：文件 + 数据表
```

##### 六、单个配置项便捷读写

```php
// 读：优先数据表值，其次文件默认值，都没有则返回 $default
get_addons_config_value('test', 'rewrite', '0');

// 写：仅写 addon_config 表（不动 config.php），支持数组值自动 JSON 序列化
// 成功返回 true，表不存在时返回 false
set_addons_config_value('test', 'rewrite', '1');
set_addons_config_value('test', 'options', ['a' => 1, 'b' => '中文']);
```

##### 七、使用场景建议

| 场景 | 推荐方式 |
|------|----------|
| 插件开发者定义配置项结构与默认值 | 随插件包提供 `config.php` |
| 后台配置页整体保存 | `set_addons_config()`（双写） |
| 插件业务代码读取配置 | `get_addons_config($name, true)` 或 `get_addons_config_value()` |
| 插件运行时动态记录状态/开关 | `set_addons_config_value()`（仅写表，不碰文件） |
| 卸载插件清理配置 | 删除 `addon_config` 表中 `addon={插件名}` 的记录，并清除 `addon_config_{插件名}` 缓存 |

#### 多应用模式说明

> v1.0.4+ 版本针对 ThinkPHP 多应用模式做了安全隔离

在多应用项目中（如 admin、api、index、install 等），插件系统有以下限制：

1. **路由注册**：插件路由仅在 `index` 应用中注册，admin/api/install 等应用不会加载插件路由
2. **路由执行**：`Route::execute()` 仅允许在 `index` 应用上下文中执行，其他应用访问插件路由将返回 404
3. **禁用插件过滤**：配置中的自定义路由，如果对应插件已禁用或不存在，路由将被自动跳过

这意味着插件的前台页面功能仅在 `index` 应用中可用。如果需要在管理后台使用插件功能，应通过插件的服务绑定（`service.json`）或钩子机制来实现，而非直接访问插件控制器路由。

#### 插件目录结构
##### 最终生成的目录结构为

```html
www  WEB部署目录（或者子目录）
├─addons        插件目录
├─app           应用目录
│  ├─controller      控制器目录
│  ├─model           模型目录
│  ├─ ...            更多类库目录
│  │
│  ├─common.php         公共函数文件
│  └─event.php          事件定义文件
│
├─config        配置目录
│  ├─addons.php         插件配置
│  ├─app.php            应用配置
│  ├─cache.php          缓存配置
│  ├─console.php        控制台配置
│  ├─cookie.php         Cookie配置
│  ├─database.php       数据库配置
│  ├─filesystem.php     文件磁盘配置
│  ├─lang.php           多语言配置
│  ├─log.php            日志配置
│  ├─middleware.php     中间件配置
│  ├─route.php          URL和路由配置
│  ├─session.php        Session配置
│  ├─trace.php          Trace配置
│  └─view.php           视图配置
│
├─view           视图目录
├─route                 路由定义目录
│  ├─route.php          路由定义文件
│  └─ ...   
│
├─public        WEB目录(对外访问目录)
│  ├─index.php          入口文件
│  ├─router.php         快速测试文件
│  └─.htaccess          用于apache的重写
│
├─extend                扩展类库目录
├─runtime               应用的运行时目录(可写,可定制)
├─vendor                Composer类库目录
├─.example.env          环境变量示例文件
├─composer.json         composer 定义文件
├─README.md             README 文件
├─think                 命令行入口文件
```
