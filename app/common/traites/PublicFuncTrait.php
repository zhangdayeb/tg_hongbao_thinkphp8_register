<?php


namespace app\common\traites;

trait PublicFuncTrait
{
    public function edit()
    {
        $arr= [1,2,3,4,5,6,7,8];

        echo 'trait edit';
    }

    public function desc()
    {
        echo 'trait desc';
    }
}