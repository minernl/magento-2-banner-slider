<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_BannerSlider
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\BannerSlider\Model\ResourceModel;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Model\AbstractModel;

/**
 * Class Banner
 * @package Mageplaza\BannerSlider\Model\ResourceModel
 */
class Banner extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Date model
     * 
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    /**
     * Slider relation model
     * 
     * @var string
     */
    protected $bannerSliderTable;

    /**
     * Event Manager
     * 
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * constructor
     * 
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     */
    public function __construct(
        DateTime $date,
        ManagerInterface $eventManager,
        Context $context
    )
    {
        $this->date         = $date;
        $this->eventManager = $eventManager;
        parent::__construct($context);
        $this->bannerSliderTable = $this->getTable('mageplaza_bannerslider_banner_slider');
    }


    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_bannerslider_banner', 'banner_id');
    }

    /**
     * Retrieves Banner Name from DB by passed id.
     *
     * @param string $id
     * @return string|bool
     */
    public function getBannerNameById($id)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()
            ->from($this->getMainTable(), 'name')
            ->where('banner_id = :banner_id');
        $binds = ['banner_id' => (int)$id];
        return $adapter->fetchOne($select, $binds);
    }

    /**
     * before save callback
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _beforeSave(AbstractModel $object)
    {
        //set default Update At and Create At time post
        $object->setUpdatedAt($this->date->date());
        if ($object->isObjectNew()) {
            $object->setCreatedAt($this->date->date());
        }

        if ($object->getUrlBanner() && strpos($object->getUrlBanner(),'http') === false) {
            $object->setUrlBanner('https://'.$object->getUrlBanner());
        }

        return $this;
    }

    /**
     * after save callback
     *
     * @param \Magento\Framework\Model\AbstractModel|\Mageplaza\BannerSlider\Model\Banner $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->saveSliderRelation($object);
        return parent::_afterSave($object);
    }

    /**
     * @param \Mageplaza\BannerSlider\Model\Banner $banner
     * @return array
     */
    public function getSlidersPosition(\Mageplaza\BannerSlider\Model\Banner $banner)
    {
        $select = $this->getConnection()->select()->from(
            $this->bannerSliderTable,
            ['slider_id', 'position']
        )
        ->where(
            'banner_id = :banner_id'
        );
        $bind = ['banner_id' => (int)$banner->getId()];
        return $this->getConnection()->fetchPairs($select, $bind);
    }

    /**
     * @param \Mageplaza\BannerSlider\Model\Banner $banner
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function saveSliderRelation(\Mageplaza\BannerSlider\Model\Banner $banner)
    {
        $banner->setIsChangedSliderList(false);
        $id = $banner->getId();
        $sliders = $banner->getSlidersIds();
        if ($sliders === null) {
            return $this;
        }
        $oldSliders = $banner->getSliderIds();

        $insert = array_diff($sliders, $oldSliders);
        $delete = array_diff($oldSliders, $sliders);
        $adapter = $this->getConnection();

        if (!empty($delete)) {
            $condition = ['slider_id IN(?)' => $delete, 'banner_id=?' => $id];
            $adapter->delete($this->bannerSliderTable, $condition);
        }
        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $tagId) {
                $data[] = [
                    'banner_id'  => (int)$id,
                    'slider_id'   => (int)$tagId,
                    'position' => 1
                ];
            }
            $adapter->insertMultiple($this->bannerSliderTable, $data);
        }
        if (!empty($insert) || !empty($delete)) {
            $sliderIds = array_unique(array_merge(array_keys($insert), array_keys($delete)));
            $this->eventManager->dispatch(
                'mageplaza_bannerslider_banner_change_sliders',
                ['banner' => $banner, 'slider_ids' => $sliderIds]);
        }
        if (!empty($insert) || !empty($delete)) {
            $banner->setIsChangedSliderList(true);
            $sliderIds = array_keys($insert + $delete);
            $banner->setAffectedSliderIds($sliderIds);
        }

        return $this;
    }

    /**
     * @param \Mageplaza\BannerSlider\Model\Banner $banner
     * @return array
     */
    public function getSliderIds(\Mageplaza\BannerSlider\Model\Banner $banner)
    {
        $adapter = $this->getConnection();
        $select  = $adapter->select()->from(
            $this->bannerSliderTable,
            'slider_id'
        )
                           ->where(
                               'banner_id = ?',
                               (int)$banner->getId()
                           );

        return $adapter->fetchCol($select);
    }
}
