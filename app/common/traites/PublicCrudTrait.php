<?php


namespace app\common\traites;

use app\common\model\MoneyLog;
use app\common\validate\Status;
use think\exception\ValidateException;

/**
 * 模型公用删除
 * Trait PublicCrudTrait
 * @package app\common\traites
 */
trait PublicCrudTrait
{
    //模型软删除
    public function del()
    {
        $id = $this->request->post('id', 0);
        if ($id < 1) return $this->failed('ID错误');

        //模型删除
        $del = $this->model->del($id);
        if ($del) return $this->success([]);
        return $this->failed('删除失败，数据不存在');
    }

    public function edit()
    {
        echo 'trait edit';
    }

    public function desc()
    {
        echo 'trait desc';
    }

    /**
     * 查询
     * @return mixed
     */
    public function detail()
    {
        //过滤数据
        $postField = 'id';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        //验证数据
        try {
            validate(Status::class)->scene('detail')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getError());
        }

        //查询
        $user = $this->model->find($post['id']);
        if ($user) return $this->success($user);
        return $this->failed('数据不存在');
    }

    /**
     * 状态切换 上下架
     */
    public function status()
    {
        //过滤数据
        $postField = 'id,status';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        try {
            validate(Status::class)->scene('status')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getError());
        }

        $save = $this->model->setStatus($post);
        if ($save) return $this->success([]);
        return $this->failed('修改失败');
    }

    /**
     * 状态切换 上下架
     */
    public function is_show()
    {
        //过滤数据
        $postField = 'id,show';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        try {
            validate(Status::class)->scene('show')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getError());
        }

        $save = $this->model->where('id',$post['id'])->update(['is_show'=>$post['show']]);
        if ($save) return $this->success([]);
        return $this->failed('修改失败');
    }
}