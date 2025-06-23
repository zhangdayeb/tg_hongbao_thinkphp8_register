<?php


namespace app\common\traites;


trait GetTreeTrait
{
    public function fillModelBackends($models)
    {

        $list = [];
        foreach ($models as $model) {

            $listItem = $this->fillModelBackend($model);
            $list[] = $listItem;
        }
        return $list;
    }

    public function fillModelBackend($model)
    {
        if ($model['id'] ==0 )return $model;
        $loadList = $this->model->where(['pid' => $model['id']])->select();
        if (count($loadList) > 0) {
            $model['children'] = $this->fillModelBackends($loadList);
        }
        //else {
        //    $model['children'] = [];
        //}
        return $model;
    }
}