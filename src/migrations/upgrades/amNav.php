<?php

namespace studioespresso\navigate\migrations\upgrades;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;

class amNav extends Migration
{
    public function safeUp()
    {
        if (!Craft::$app->getDb()->tableExists('{{%amnav_navs}}')) {
            return true;
        }
        $amNavs = (new Query())
            ->select(['*'])
            ->from(['{{%amnav_navs}}'])
            ->all();
        foreach ($amNavs as $key => $amNav) {
            $nav = Navigate::getInstance()->navigate->getNavigationByHandle($amNav['handle']);
            if (!$nav) {
                $nav = new NavigationModel();
            }
            $nav->id = $amNav['id'];
            $nav->title = $amNav['name'];
            $nav->handle = $amNav['handle'];
            $settings = Json::decode($amNav['settings']);
            $nav->levels = $settings['maxLevels'] ?? '';
            $nav->adminOnly = true;

            Navigate::getInstance()->navigate->saveNavigation($nav);
        }

        $navs = Navigate::getInstance()->navigate->getAllNavigations();
        foreach ($navs as $nav) {
            $amNavNodes = (new Query())
                ->select(['*'])
                ->from(['{{%amnav_nodes}}'])
                ->where(['navId' => $nav['id']])
                ->orderBy('parentId ASC, order ASC')
                ->all();
            foreach ($amNavNodes as $amNavNode) {
                try {

                    $node = new NodeModel();
                    $node->name = $amNavNode['name'];
                    $node->enabled = $amNavNode['enabled'];
                    $node->navId = $nav->id;
                    $node->url = $amNavNode['url'];
                    $node->classes = $amNavNode['listClass'];
                    $node->blank = $amNavNode['blank'];
                    $node->order = $amNavNode['order'];
                    $locale = $amNavNode['locale'];
                    $site = Craft::$app->getSites()->getSiteByHandle($locale);
                    if ($site) {
                        $node->siteId = $site->id;
                    }

                    if ($amNavNode['elementType'] === 'Entry') {
                        $node->type = 'element';
                        $node->elementType = 'entry';
                        $node->elementId = $amNavNode['elementId'];
                    } else if ($amNavNode['elementType'] === 'Category') {
                        $node->type = 'element';
                        $node->elementType = 'category';
                        $node->elementId = $amNavNode['elementId'];
                    } else if ($amNavNode['elementType'] === 'Asset') {
                        $node->type = 'element';
                        $node->elementType = 'asset';
                        $node->elementId = $amNavNode['elementId'];
                    } else if ($amNavNode['url']) {
                        $node->type = 'url';
                        $node->url = $amNavNode['url'];
                    }

                    Navigate::getInstance()->nodes->save($node);
                } catch (\Throwable $e) {
                    Craft::error("Error migratining $node->name");
                }
            }
        }
    }

    public function safeDown()
    {
        return false;
    }
}