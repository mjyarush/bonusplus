<?php
class shopBonusplusWaContactCategoriesModel extends waModel
{
    protected $table = 'wa_contact_categories';

    public function getUserCategory($contact_id){

        $res = $this->query("SELECT category_id FROM `{$this->table}` WHERE contact_id=?", $contact_id)->fetchAll();
        $res['count'] = $this->query("SELECT category_id FROM `{$this->table}` WHERE contact_id=?", $contact_id)->count();

        //Clear
        if(!empty($res)){
            return $res;
        }else{
            return 0;
        }
    }
}