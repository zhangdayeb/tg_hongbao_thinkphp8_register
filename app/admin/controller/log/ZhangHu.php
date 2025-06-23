<?php

namespace app\admin\controller\log;

use app\admin\controller\Base;
use think\facade\Db;
use think\facade\Request;
use think\facade\Log;
use think\Exception;

class ZhangHu extends Base
{
    /**
     * 公司收款账户管理控制器
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * 获取收款账户列表
     * 路由: POST /api/zhanghu/list
     */
    public function list()
    {
        try {
            $page = (int)$this->request->post('page', 1);
            $limit = (int)$this->request->post('limit', 10);
            $methodCode = $this->request->post('methodCode', '');
            $isActive = $this->request->post('isActive', '');
            $accountName = $this->request->post('accountName', '');
            $start = $this->request->post('start', '');
            $end = $this->request->post('end', '');

            $query = Db::name('dianji_deposit_accounts');

            // 应用筛选条件
            if (!empty($methodCode)) {
                $query->where('method_code', $methodCode);
            }
            if ($isActive !== '') {
                $query->where('is_active', (int)$isActive);
            }
            if (!empty($accountName)) {
                $query->where('account_name', 'like', '%' . $accountName . '%');
            }
            if (!empty($start)) {
                $query->where('created_at', '>=', $start);
            }
            if (!empty($end)) {
                $query->where('created_at', '<=', $end);
            }

            $total = $query->count();
            $offset = ($page - 1) * $limit;
            
            $list = $query->order('created_at', 'desc')
                ->limit($offset, $limit)
                ->select()
                ->toArray();

            // 格式化数据
            foreach ($list as &$item) {
                $item = $this->formatAccountData($item);
            }

            $result = [
                'data' => $list,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit,
                'last_page' => ceil($total / $limit)
            ];

            return json(['code' => 1, 'message' => '获取成功', 'data' => $result]);

        } catch (Exception $e) {
            Log::error('获取账户列表失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => '获取数据失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 获取账户详情
     * 路由: POST /api/zhanghu/detail
     */
    public function detail()
    {
        try {
            $id = (int)$this->request->post('id');
            
            if (!$id) {
                return json(['code' => 0, 'message' => '缺少必要参数']);
            }

            $account = Db::name('dianji_deposit_accounts')
                ->where('id', $id)
                ->find();

            if (!$account) {
                return json(['code' => 0, 'message' => '账户不存在']);
            }

            $account = $this->formatAccountData($account);

            return json(['code' => 1, 'message' => '获取成功', 'data' => $account]);

        } catch (Exception $e) {
            Log::error('获取账户详情失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => '获取详情失败']);
        }
    }

    /**
     * 添加账户
     * 路由: POST /api/zhanghu/add
     */
    public function add()
    {
        try {
            $data = $this->validateAccountData();
            
            if (isset($data['error'])) {
                return json(['code' => 0, 'message' => $data['error']]);
            }

            // 检查账户是否已存在
            $exists = $this->checkAccountExists($data);
            if ($exists) {
                return json(['code' => 0, 'message' => '账户已存在']);
            }


            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');

            $id = Db::name('dianji_deposit_accounts')->insertGetId($data);

            if ($id) {
                return json(['code' => 1, 'message' => '添加成功', 'data' => ['id' => $id]]);
            } else {
                return json(['code' => 0, 'message' => '添加失败']);
            }

        } catch (Exception $e) {
            Log::error('添加账户失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 编辑账户
     * 路由: POST /api/zhanghu/edit
     */
    public function edit()
    {
        try {
            $id = (int)$this->request->post('id');
            if (!$id) {
                return json(['code' => 0, 'message' => '缺少账户ID']);
            }

            // 先获取现有账户信息
            $account = Db::name('dianji_deposit_accounts')->where('id', $id)->find();
            if (!$account) {
                return json(['code' => 0, 'message' => '账户不存在']);
            }

            $data = $this->validateAccountData(false, $account['method_code']);
            
            if (isset($data['error'])) {
                return json(['code' => 0, 'message' => $data['error']]);
            }

            // 检查是否与其他账户重复
            $exists = $this->checkAccountExists($data, $id);
            if ($exists) {
                return json(['code' => 0, 'message' => '账户信息与其他账户重复']);
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $result = Db::name('dianji_deposit_accounts')
                ->where('id', $id)
                ->update($data);

            if ($result !== false) {
                return json(['code' => 1, 'message' => '更新成功']);
            } else {
                return json(['code' => 0, 'message' => '更新失败']);
            }

        } catch (Exception $e) {
            Log::error('更新账户失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 删除账户
     * 路由: POST /api/zhanghu/del
     */
    public function del()
    {
        try {
            $id = (int)$this->request->post('id');
            
            if (!$id) {
                return json(['code' => 0, 'message' => '缺少账户ID']);
            }

            // 检查账户是否存在
            $account = Db::name('dianji_deposit_accounts')->where('id', $id)->find();
            if (!$account) {
                return json(['code' => 0, 'message' => '账户不存在']);
            }

            $result = Db::name('dianji_deposit_accounts')->where('id', $id)->delete();

            if ($result) {
                return json(['code' => 1, 'message' => '删除成功']);
            } else {
                return json(['code' => 0, 'message' => '删除失败']);
            }

        } catch (Exception $e) {
            Log::error('删除账户失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 切换账户状态
     * 路由: POST /api/zhanghu/status
     */
    public function status()
    {
        try {
            $id = (int)$this->request->post('id');
            $isActive = (int)$this->request->post('isActive');
            
            if (!$id) {
                return json(['code' => 0, 'message' => '缺少账户ID']);
            }

            $result = Db::name('dianji_deposit_accounts')
                ->where('id', $id)
                ->update([
                    'is_active' => $isActive,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            if ($result !== false) {
                $statusText = $isActive ? '启用' : '禁用';
                return json(['code' => 1, 'message' => $statusText . '成功']);
            } else {
                return json(['code' => 0, 'message' => '操作失败']);
            }

        } catch (Exception $e) {
            Log::error('切换账户状态失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 批量操作账户状态
     * 路由: POST /api/zhanghu/batch_status
     */
    public function batchStatus()
    {
        try {
            $ids = $this->request->post('ids', []);
            $isActive = (int)$this->request->post('isActive');
            
            if (empty($ids)) {
                return json(['code' => 0, 'message' => '请选择要操作的账户']);
            }

            $result = Db::name('dianji_deposit_accounts')
                ->where('id', 'in', $ids)
                ->update([
                    'is_active' => $isActive,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            $statusText = $isActive ? '启用' : '禁用';
            return json(['code' => 1, 'message' => "批量{$statusText}成功，共处理 {$result} 个账户"]);

        } catch (Exception $e) {
            Log::error('批量切换状态失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 获取统计数据
     * 路由: POST /api/zhanghu/statistics
     */
    public function statistics()
    {
        try {
            $methodCode = $this->request->post('methodCode', '');
            $isActive = $this->request->post('isActive', '');
            $start = $this->request->post('start', '');
            $end = $this->request->post('end', '');

            $query = Db::name('dianji_deposit_accounts');

            // 应用筛选条件
            if (!empty($methodCode)) {
                $query->where('method_code', $methodCode);
            }
            if ($isActive !== '') {
                $query->where('is_active', (int)$isActive);
            }
            if (!empty($start)) {
                $query->where('created_at', '>=', $start);
            }
            if (!empty($end)) {
                $query->where('created_at', '<=', $end);
            }

            $stats = $query->field([
                'COUNT(*) as total_count',
                'SUM(CASE WHEN method_code = "aba" THEN 1 ELSE 0 END) as aba_count',
                'SUM(CASE WHEN method_code = "huiwang" THEN 1 ELSE 0 END) as huiwang_count',
                'SUM(CASE WHEN method_code = "usdt" THEN 1 ELSE 0 END) as usdt_count',
                'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count',
                'SUM(daily_limit) as total_daily_limit'
            ])->find();

            $statistics = [
                'totalCount' => (int)$stats['total_count'],
                'abaCount' => (int)$stats['aba_count'],
                'huiwangCount' => (int)$stats['huiwang_count'],
                'usdtCount' => (int)$stats['usdt_count'],
                'activeCount' => (int)$stats['active_count'],
                'totalDailyLimit' => number_format($stats['total_daily_limit'] ?: 0, 2)
            ];

            return json(['code' => 1, 'message' => '获取成功', 'data' => $statistics]);

        } catch (Exception $e) {
            Log::error('获取统计数据失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => '获取统计数据失败']);
        }
    }

    /**
     * 导出账户列表
     * 路由: POST /api/zhanghu/export
     */
    public function export()
    {
        try {
            $conditions = $this->request->post();
            
            $exportData = [
                'filename' => '收款账户列表_' . date('YmdHis') . '.xlsx',
                'downloadUrl' => '/download/deposit_accounts_' . date('YmdHis') . '.xlsx',
                'totalRecords' => 0,
                'exportTime' => date('Y-m-d H:i:s')
            ];

            return json(['code' => 1, 'message' => '导出成功', 'data' => $exportData]);

        } catch (Exception $e) {
            Log::error('导出失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => '导出失败']);
        }
    }

    /**
     * 获取支付方式配置
     * 路由: POST /api/zhanghu/payment_methods
     */
    public function paymentMethods()
    {
        try {
            $config = [
                [
                    'code' => 'aba',
                    'name' => 'ABA银行',
                    'icon' => 'el-icon-bank-card',
                    'color' => 'primary',
                    'enabled' => true
                ],
                [
                    'code' => 'huiwang',
                    'name' => '汇旺支付',
                    'icon' => 'el-icon-mobile',
                    'color' => 'warning',
                    'enabled' => true
                ],
                [
                    'code' => 'usdt',
                    'name' => 'USDT钱包',
                    'icon' => 'el-icon-coin',
                    'color' => 'success',
                    'enabled' => true,
                    'networks' => ['TRC20', 'ERC20', 'BSC']
                ]
            ];

            return json(['code' => 1, 'message' => '获取成功', 'data' => $config]);

        } catch (Exception $e) {
            Log::error('获取支付方式配置失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => '获取配置失败']);
        }
    }

    /**
     * 更新账户使用统计
     * 路由: POST /api/zhanghu/update_usage
     */
    public function updateUsage()
    {
        try {
            $id = (int)$this->request->post('id');
            
            if (!$id) {
                return json(['code' => 0, 'message' => '缺少账户ID']);
            }

            $result = Db::name('dianji_deposit_accounts')
                ->where('id', $id)
                ->inc('usage_count')
                ->update(['last_used_at' => date('Y-m-d H:i:s')]);

            if ($result !== false) {
                return json(['code' => 1, 'message' => '更新成功']);
            } else {
                return json(['code' => 0, 'message' => '更新失败']);
            }

        } catch (Exception $e) {
            Log::error('更新使用统计失败: ' . $e->getMessage());
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 验证账户数据
     * @param bool $isAdd 是否为添加操作
     * @param string $existingMethodCode 现有的支付方式代码（编辑时使用）
     */
    private function validateAccountData($isAdd = true, $existingMethodCode = '')
    {
        $data = [];
        
        // 通用字段
        if ($isAdd) {
            $data['method_code'] = $this->request->post('methodCode', '');
            if (empty($data['method_code'])) {
                return ['error' => '请选择支付方式'];
            }
        } else {
            // 编辑时，如果前端传了methodCode就用，否则使用现有的
            $data['method_code'] = $this->request->post('methodCode', $existingMethodCode);
        }

        $data['account_name'] = $this->request->post('accountName', '');
        if (empty($data['account_name'])) {
            return ['error' => '请输入账户名称'];
        }

        $data['is_active'] = (int)$this->request->post('isActive', 1);
        $data['daily_limit'] = $this->request->post('dailyLimit', 0);
        $data['balance_limit'] = $this->request->post('balanceLimit', 9999999);
        $data['remark'] = $this->request->post('remark', '');
        $data['qr_code_url'] = $this->request->post('qrCodeUrl', '');

        // 根据支付方式验证特定字段
        $methodCode = $data['method_code'];
        
        switch ($methodCode) {
            case 'aba':
                $data['account_number'] = $this->request->post('accountNumber', '');
                $data['bank_name'] = $this->request->post('bankName', '');
                if (empty($data['account_number'])) {
                    return ['error' => '请输入银行账户号码'];
                }
                if (empty($data['bank_name'])) {
                    return ['error' => '请输入银行名称'];
                }
                // 清空其他支付方式的字段
                $data['phone_number'] = null;
                $data['wallet_address'] = null;
                $data['network_type'] = null;
                break;

            case 'huiwang':
                $data['account_number'] = $this->request->post('accountNumber', '');
                $data['phone_number'] = $this->request->post('phoneNumber', '');
                if (empty($data['account_number'])) {
                    return ['error' => '请输入汇旺账号'];
                }
                if (empty($data['phone_number'])) {
                    return ['error' => '请输入手机号码'];
                }
                // 清空其他支付方式的字段
                $data['bank_name'] = null;
                $data['wallet_address'] = null;
                $data['network_type'] = null;
                break;

            case 'usdt':
                $data['wallet_address'] = $this->request->post('walletAddress', '');
                $data['network_type'] = $this->request->post('networkType', '');
                if (empty($data['wallet_address'])) {
                    return ['error' => '请输入钱包地址'];
                }
                if (empty($data['network_type'])) {
                    return ['error' => '请选择网络类型'];
                }
                // 清空其他支付方式的字段
                $data['account_number'] = null;
                $data['bank_name'] = null;
                $data['phone_number'] = null;
                break;

            default:
                return ['error' => '不支持的支付方式'];
        }

        return $data;
    }

    /**
     * 检查账户是否已存在
     */
    private function checkAccountExists($data, $excludeId = 0)
    {
        $query = Db::name('dianji_deposit_accounts')
            ->where('method_code', $data['method_code']);

        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        switch ($data['method_code']) {
            case 'aba':
                $query->where('account_number', $data['account_number']);
                break;
            case 'huiwang':
                $query->where('account_number', $data['account_number']);
                break;
            case 'usdt':
                $query->where('wallet_address', $data['wallet_address']);
                break;
        }

        return $query->count() > 0;
    }

    /**
     * 格式化账户数据
     */
    private function formatAccountData($data)
    {
        $formatted = [
            'id' => $data['id'],
            'methodCode' => $data['method_code'],
            'accountName' => $data['account_name'],
            'accountNumber' => $data['account_number'] ?? '',
            'bankName' => $data['bank_name'] ?? '',
            'phoneNumber' => $data['phone_number'] ?? '',
            'walletAddress' => $data['wallet_address'] ?? '',
            'networkType' => $data['network_type'] ?? '',
            'qrCodeUrl' => $data['qr_code_url'] ?? '',
            'isActive' => (int)$data['is_active'],
            'dailyLimit' => $data['daily_limit'] ?? 0,
            'balanceLimit' => $data['balance_limit'] ?? null,
            'usageCount' => (int)($data['usage_count'] ?? 0),
            'lastUsedAt' => $data['last_used_at'] ?? null,
            'remark' => $data['remark'] ?? '',
            'createdAt' => $data['created_at'],
            'updatedAt' => $data['updated_at']
        ];

        return $formatted;
    }
}