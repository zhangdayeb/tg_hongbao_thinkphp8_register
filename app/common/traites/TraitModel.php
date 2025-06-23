<?php

namespace app\common\traites;

/**
 * Trait TraitModel
 * 通用模型操作Trait，封装增删改查和一些辅助方法
 */
trait TraitModel
{
    /**
     * 直接删除指定主键的数据（物理删除）
     * @param int $id 主键ID
     * @return bool 删除成功返回true，失败返回false
     */
    public function del($id)
    {
        $find = $this->find($id);
        if (empty($find)) {
            return false;
        }
        return $find->delete();
    }

    /**
     * 添加数据
     * @param array $data 数据数组
     * @param bool $type 是否单条添加，true为单条，false为多条批量添加
     * @return bool|int 成功返回插入条数或bool，失败false
     */
    public function add(array $data, bool $type = true)
    {
        if ($type) {
            // 单条插入
            return $this->insert($data);
        }
        // 多条插入
        return $this->insertAll($data);
    }

    /**
     * 软删除 - 这里预留，示例返回空
     * @param int $id 主键ID
     * @return string
     */
    public function deletes($id)
    {
        // 需要模型使用软删除 trait（如 SoftDelete）后实现
        return '';
    }

    /**
     * 设置状态字段
     * @param array $post ['id'=>int, 'status'=>int]
     * @return bool|int 成功返回影响行数，失败false
     */
    public function setStatus(array $post)
    {
        $id = intval($post['id'] ?? 0);
        $status = intval($post['status'] ?? 0);

        if ($id < 1) {
            return false;
        }

        $find = $this->find($id);
        if (!$find) {
            return false;
        }

        return $find->save(['status' => $status]);
    }

    /**
     * 获取图片缩略图URL属性转换器
     * 支持多张图片逗号分割拼接完整URL
     * @param string|array $value 图片路径字符串或数组
     * @return array|string 返回完整URL字符串或数组
     */
    public function getThumbUrlAttr($value)
    {
        if (empty($value)) {
            return '';
        }

        if (is_array($value)) {
            return '';
        }

        $value = explode(',', $value);

        if (count($value) > 1) {
            foreach ($value as $key => $v) {
                $value[$key] = config('ToConfig.app_update.image_url') . $v;
            }
            return $value;
        }

        return config('ToConfig.app_update.image_url') . $value[0];
    }

    /**
     * 获取视频URL属性转换器
     * @param string|null $value 视频路径
     * @return string 完整视频URL或空字符串
     */
    public function getVideoUrlAttr($value)
    {
        if (!empty($value)) {
            return config('ToConfig.app_update.image_url') . $value;
        }
        return '';
    }

    /**
     * 代理商查看代理商推广的用户充值等权限条件
     * 不排除自己
     * @return array 权限筛选条件数组
     */
    public static function whereMap()
    {
        $map = [];

        // 示例逻辑，判断当前登录用户角色是否为代理商
        if (session('admin_user.role') == 2) {
            $map = ['b.market_uid' => session('admin_user.id')];
        }

        return $map;
    }

    /**
     * 代理商查看用户代理权限条件
     * 排除自己
     * @return array 权限筛选条件数组
     */
    public static function whereMapUser()
    {
        $map = [];

        if (session('admin_user.role') == 2) {
            $map = ['b.market_uid' => session('admin_user.id')];
        }

        return $map;
    }
}
