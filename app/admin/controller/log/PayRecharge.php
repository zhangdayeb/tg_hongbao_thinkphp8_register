<?php

namespace app\admin\controller\log;

use app\admin\controller\Base;
use app\common\model\PayRecharge as models;
use app\common\model\User;
use app\common\model\MoneyLog;
use app\common\traites\PublicCrudTrait;
use think\exception\ValidateException;
use think\facade\Db;

class PayRecharge extends Base
{
    protected $model;
    use PublicCrudTrait;
    
    /**
     * 充值控制器
     */
    public function initialize()
    {
        $this->model = new models();
        parent::initialize();
    }

    /**
     * 充值列表
     */
    public function index()
    {
        // 当前页
        $page = $this->request->post('page', 1);
        // 每页显示数量
        $limit = $this->request->post('limit', 10);
        // 查询搜索条件
        $post = $this->request->post();
        $map = $date = [];
        
        // 状态筛选
        isset($post['status']) && $post['status'] != '' && $map[] = ['a.status', '=', $post['status']];
        // 用户账号模糊查询
        isset($post['user_name']) && $map[] = ['b.user_name', 'like', '%' . $post['user_name'] . '%'];
        // 支付方式筛选
        isset($post['payment_method']) && $post['payment_method'] != '' && $map[] = ['a.payment_method', '=', $post['payment_method']];
        
        // 日期范围筛选
        if (isset($post['start_date']) && isset($post['end_date'])) {
            $date['start'] = $post['start_date'];
            $date['end'] = $post['end_date'];
        }
        
        $list = $this->model->page_list($map, $limit, $page, $date);
        return $this->success($list);
    }

    /**
     * 充值审核通过
     */
    public function pass()
    {
        // 过滤数据
        $postField = 'id,admin_remarks';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        
        // 检测订单
        $find = $this->model->where('id', (int)$post['id'])->where('status', 0)->find();
        if (!$find) {
            return $this->failed('该充值订单已处理或不存在');
        }

        $User = new User();
        $user = $User->where('id', $find['user_id'])->find();
        if (!$user) {
            return $this->failed('用户不存在');
        }

        Db::startTrans();
        try {
            // 更新用户余额
            Db::name('common_user')->where('id', $find['user_id'])->inc('money_balance', $find['money'])->update();
            
            // 记录资金流水到 money_log 表
            $moneyLogData = [
                'create_time' => date('Y-m-d H:i:s'),
                'type' => 1, // 收入
                'status' => 101, // 充值
                'money_before' => $user['money_balance'],
                'money_end' => $user['money_balance'] + $find['money'],
                'money' => $find['money'],
                'uid' => $find['user_id'],
                'source_id' => $post['id'],
                'market_uid' => session('admin_user')['id'] ?? 0,
                'mark' => '充值审核通过 - 订单号:' . $find['order_number'] . (isset($post['admin_remarks']) ? ' - ' . $post['admin_remarks'] : '')
            ];
            Db::name('common_pay_money_log')->insert($moneyLogData);

            // 更新充值订单状态
            $updateData = [
                'status' => 1,
                'success_time' => date('Y-m-d H:i:s'),
                'admin_uid' => session('admin_user')['id'] ?? 0,
                'admin_remarks' => $post['admin_remarks'] ?? '充值审核通过'
            ];
            $this->model->where('id', $post['id'])->update($updateData);

            Db::commit();
            return $this->success([]);
        } catch (ValidateException $e) {
            Db::rollback();
            return $this->failed($e->getError());
        } catch (\Exception $e) {
            Db::rollback();
            return $this->failed('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 充值审核拒绝
     */
    public function refuse()
    {
        // 过滤数据
        $postField = 'id,admin_remarks';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        
        // 检测订单
        $find = $this->model->where('id', (int)$post['id'])->where('status', 0)->find();
        if (!$find) {
            return $this->failed('该充值订单已处理或不存在');
        }

        try {
            // 更新充值订单状态为拒绝
            $updateData = [
                'status' => 2,
                'success_time' => date('Y-m-d H:i:s'),
                'admin_uid' => session('admin_user')['id'] ?? 0,
                'admin_remarks' => $post['admin_remarks'] ?? '充值审核拒绝'
            ];
            $save = $this->model->where('id', $post['id'])->update($updateData);
            
            if ($save) {
                return $this->success([]);
            }
            return $this->failed('操作失败');
        } catch (ValidateException $e) {
            return $this->failed($e->getError());
        } catch (\Exception $e) {
            return $this->failed('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 充值统计数据
     */
    public function statistics()
    {
        try {
            // 今日充值笔数
            $today_count = $this->model->whereTime('create_time', 'today')->count();
            
            // 今日充值金额 (仅统计审核通过的)
            $today_amount = $this->model->whereTime('success_time', 'today')
                ->where('status', 1)
                ->sum('money') ?: 0;
            
            // 待审核订单数
            $pending_count = $this->model->where('status', 0)->count();
            
            // 待审核金额
            $pending_amount = $this->model->where('status', 0)->sum('money') ?: 0;
            
            return $this->success([
                'today_count' => $today_count,
                'today_amount' => number_format($today_amount, 2, '.', ''),
                'pending_count' => $pending_count,
                'pending_amount' => number_format($pending_amount, 2, '.', '')
            ]);
        } catch (\Exception $e) {
            return $this->failed('获取统计数据失败：' . $e->getMessage());
        }
    }
}