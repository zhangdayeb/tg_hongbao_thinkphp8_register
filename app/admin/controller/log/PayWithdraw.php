<?php

namespace app\admin\controller\log;

use app\admin\controller\Base;
use app\common\model\PayWithdraw as models;
use app\common\model\User;
use app\common\model\MoneyLog;
use app\common\traites\PublicCrudTrait;
use think\exception\ValidateException;
use think\facade\Db;

class PayWithdraw extends Base
{
    protected $model;
    use PublicCrudTrait;

    /**
     * 提现控制器
     */
    public function initialize()
    {
        $this->model = new models();
        parent::initialize();
    }

    /**
     * 提现列表
     */
    public function index()
    {
        // 当前页
        $page = $this->request->post('page', 1);
        // 每页显示数量
        $limit = $this->request->post('limit', 10);
        // 查询搜索条件
        $post = $this->request->post();
        $map = [];
        $date = [];
        
        // 用户账号模糊查询
        isset($post['user_name']) && $map[] = ['b.user_name', 'like', '%' . $post['user_name'] . '%'];
        // 状态筛选
        isset($post['status']) && $post['status'] != '' && $map[] = ['a.status', '=', intval($post['status'])];
        // 提现地址筛选
        isset($post['withdraw_address']) && $post['withdraw_address'] != '' && $map[] = ['a.withdraw_address', 'like', '%' . $post['withdraw_address'] . '%'];
        
        // 日期范围筛选
        if (isset($post['start_date']) && isset($post['end_date'])) {
            $date['start'] = $post['start_date'];
            $date['end'] = $post['end_date'];
        }
        
        $list = $this->model->page_list($map, $limit, $page, $date);
        return $this->success($list);
    }

/**
     * 提现审核通过
     */
    public function pass()
    {
        // 过滤数据 - 适配前端参数
        $postField = 'id,msg,transaction_hash';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        
        // 记录请求日志
        \think\facade\Log::info('提现审核通过请求', [
            'post_data' => $post,
            'admin_id' => session('admin_user')['id'] ?? 0
        ]);
        
        // 单个订单处理
        $orderId = (int)$post['id'];
        if ($orderId <= 0) {
            return $this->failed('订单ID无效');
        }
        
        // 检测订单是否存在
        $find = $this->model->where('id', $orderId)->find();
        if (!$find) {
            return $this->failed('提现订单不存在');
        }
        
        // 检查订单状态
        if ($find['status'] == 1) {
            return $this->success([], '该订单已经审核通过');
        }
        
        if ($find['status'] != 0) {
            return $this->failed('该订单状态异常，无法审核');
        }

        Db::startTrans();
        try {
            // 更新提现订单状态
            $updateData = [
                'status' => 1,
                'success_time' => date('Y-m-d H:i:s'),
                'admin_uid' => session('admin_user')['id'] ?? 0,
                'msg' => $post['msg'] ?? '提现审核通过'
            ];
            
            // 如果填写了交易哈希，添加到更新数据中
            if (!empty($post['transaction_hash'])) {
                $updateData['transaction_hash'] = $post['transaction_hash'];
            }
            
            $result = $this->model->where('id', $find['id'])->update($updateData);
            
            \think\facade\Log::info('提现审核更新结果', [
                'order_id' => $find['id'],
                'update_result' => $result,
                'update_data' => $updateData
            ]);
            
            Db::commit();
            
            \think\facade\Log::info('提现审核通过成功', [
                'order_id' => $find['id'],
                'order_number' => $find['order_number']
            ]);
            
            return $this->success('提现审核通过成功');
            
        } catch (ValidateException $e) {
            Db::rollback();
            \think\facade\Log::error('提现审核失败-验证异常', [
                'order_id' => $orderId,
                'error' => $e->getError()
            ]);
            return $this->failed('操作失败：' . $e->getError());
        } catch (\Exception $e) {
            Db::rollback();
            \think\facade\Log::error('提现审核失败-系统异常', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->failed('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 提现审核拒绝
     */
    public function refuse()
    {
        // 过滤数据 - 适配前端参数
        $postField = 'id,msg';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        
        // 单个订单处理
        $orderId = (int)$post['id'];
        if ($orderId <= 0) {
            return $this->failed('订单ID无效');
        }
        
        // 检测订单是否存在且为待审核状态
        $find = $this->model->where('id', $orderId)->where('status', 0)->find();
        if (!$find) {
            return $this->failed('该提现订单已处理或不存在');
        }

        Db::startTrans();
        try {
            // 获取用户信息
            $user = Db::name('common_user')->where('id', $find['user_id'])->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            // 1. 退回用户余额（提现金额，不含手续费）
            Db::name('common_user')->where('id', $find['user_id'])->inc('money_balance', $find['money'])->update();
            
            // 2. 记录资金流水到 money_log 表 - 提现退款
            $moneyLogData = [
                'create_time' => date('Y-m-d H:i:s'),
                'type' => 4, // 提现退款
                'status' => 112, // 提现拒绝转回
                'money_before' => $user['money_balance'],
                'money_end' => $user['money_balance'] + $find['money'],
                'money' => $find['money'],
                'uid' => $find['user_id'],
                'source_id' => $find['id'],
                'market_uid' => session('admin_user')['id'] ?? 0,
                'mark' => '提现拒绝退款 - 订单号:' . $find['order_number'] . ' - ' . ($post['msg'] ?? '提现审核拒绝')
            ];
            Db::name('common_pay_money_log')->insert($moneyLogData);

            // 3. 更新提现订单状态
            $updateData = [
                'status' => 2,
                'success_time' => date('Y-m-d H:i:s'),
                'admin_uid' => session('admin_user')['id'] ?? 0,
                'msg' => $post['msg'] ?? '提现审核拒绝'
            ];
            $this->model->where('id', $find['id'])->update($updateData);
            
            Db::commit();
            return $this->success('提现审核拒绝成功');
            
        } catch (ValidateException $e) {
            Db::rollback();
            return $this->failed('操作失败：' . $e->getError());
        } catch (\Exception $e) {
            Db::rollback();
            return $this->failed('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 提现统计数据
     */
    public function statistics()
    {
        try {
            // 今日提现笔数
            $today_count = $this->model->whereTime('create_time', 'today')->count();
            
            // 今日提现金额 (仅统计审核通过的)
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