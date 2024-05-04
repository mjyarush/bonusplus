<?php

class shopBonusplusPluginSettingsAction extends waViewAction
  {
      public function execute(){

          $plugin = wa('shop')->getPlugin('bonusplus');

          // get all contact categories
          $m = new shopBonusplusWaContactCategoryModel();
          $cat = $m->getAll();

          // set vars on template
          $this->view->assign('wa_root_path', wa()->getConfig()->getRootPath());
          $this->view->assign('category', $cat);
          $this->view->assign('user_category', $plugin->getSettings('userCategory'));
          $this->view->assign('user_card', $plugin->getSettings('userCard'));
          $this->view->assign('user_loyal', $plugin->getSettings('userLoyal'));
          $this->view->assign('settings', $plugin->getSettings());
      }

  }
