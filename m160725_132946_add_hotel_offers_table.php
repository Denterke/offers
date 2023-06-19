<?php

class m160725_132946_add_hotel_offers_table extends DbMigration
{
    public function safeUp()
    {
        $this->createTable('hotel_offer', [
            'id'        => 'int(8) unsigned NOT NULL AUTO_INCREMENT',
            'hotelId'   => 'int(4) unsigned NOT NULL',
            'partnerId' => 'int(8) unsigned NULL DEFAULT NULL',
            'fromDate'  => 'date NOT NULL',
            'toDate'    => 'date NOT NULL',
            'type'      => 'varchar(50) NOT NULL',
            'created'   => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'PRIMARY KEY (id)',
        ]);
        $this->addForeignKey('hotel_offer_hotel_fk', 'hotel_offer', 'hotelId', 'hotel', 'id');
        $this->addForeignKey('hotel_offer_partner_fk', 'hotel_offer', 'partnerId', 'partner', 'id');

        $this->createTable('offer_bonus_nights_info', [
            'id'                  => 'int(8) unsigned NOT NULL AUTO_INCREMENT',
            'offerId'             => 'int(8) unsigned NOT NULL',
            'bookingDateStart'    => 'date NOT NULL',
            'bookingDateEnd'      => 'date NOT NULL',
            'requiredNightsCount' => 'tinyint(1) unsigned NOT NULL',
            'freeNightsCount'     => 'tinyint(1) unsigned NOT NULL',
            'canMultiple'         => 'tinyint(1) unsigned NOT NULL',
            'created'             => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'PRIMARY KEY (id)',
        ]);
        $this->addForeignKey('offer_bonus_nights_info_offer_fk', 'offer_bonus_nights_info', 'offerId', 'hotel_offer', 'id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('offer_bonus_nights_info_offer_fk', 'offer_bonus_nights_info');
        $this->dropTable('offer_bonus_nights_info');
        $this->dropForeignKey('hotel_offer_hotel_fk', 'hotel_offer');
        $this->dropForeignKey('hotel_offer_partner_fk', 'hotel_offer');
        $this->dropTable('hotel_offer');
    }
}
