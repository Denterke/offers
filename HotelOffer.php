<?php

/**
 * @property Hotel $hotel
 * @property Partner|null $partner
 * @property HotelRoom[]|int[]|null $rooms
 * @property OfferBonusNightsInfo|null $bonusNightsInfo
 * @property OfferEarlyBookingInfo|null $earlyBookingInfo
 * @property OfferSpecialSeasonInfo|null $specialSeasonInfo
 * @method HotelOffer active()
 * @method HotelOffer withDetails()
 */
class HotelOffer extends ActiveRecord
{
    const FIELD_EK_HOTEL_ID = 'HotelOffer.ekHotelId';
    const FIELD_MINIMAL_COST = 'HotelOffer.minCost';
    const FIELD_DETAILS = 'HotelOffer.details';

    const TYPE_BONUS_NIGHTS = 'BONUS_NIGHTS';
    const TYPE_EARLY_BOOKING = 'EARLY_BOOKING';
    const TYPE_SPECIAL_SEASON = 'SPECIAL_SEASON';

    private static $config = [
        self::TYPE_BONUS_NIGHTS   => [
            'class'    => OfferBonusNightsInfo::class,
            'property' => 'bonusNightsInfo',
            'label'    => OfferBonusNightsInfo::LABEL,
        ],
        self::TYPE_EARLY_BOOKING  => [
            'class'    => OfferEarlyBookingInfo::class,
            'property' => 'earlyBookingInfo',
            'label'    => OfferEarlyBookingInfo::LABEL,
        ],
        self::TYPE_SPECIAL_SEASON => [
            'class'    => OfferSpecialSeasonInfo::class,
            'property' => 'specialSeasonInfo',
            'label'    => OfferSpecialSeasonInfo::LABEL,
        ],
    ];

    /** @var int */
    public $id;

    /** @var int */
    public $hotelId;

    /** @var int */
    public $partnerId;

    /** @var string */
    public $type;

    /** @var string */
    protected $fromDate;

    /** @var string */
    protected $toDate;

    /** @var string */
    public $created;

    /** @var int[] */
    protected $roomIds = [];

    /** @var CostGroupRegistry */
    protected $costGroupRegistry;

    public function __construct($scenario = 'insert')
    {
        parent::__construct($scenario);
        $this->costGroupRegistry = Yii::app()->getComponent('costGroupRegistry');
    }

    /**
     * @param string $className
     * @return ActiveRecord|HotelOffer
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'hotel_offer';
    }

    public function primaryKey()
    {
        return 'id';
    }

    public function rules()
    {
        return [
            ['fromDate', 'required'],
            ['fromDate', 'date', 'format' => 'yyyy-MM-dd'],

            ['toDate', 'required'],
            ['toDate', 'date', 'format' => 'yyyy-MM-dd'],

            ['hotelId', 'exist', 'className' => 'Hotel', 'attributeName' => 'id'],

            ['partnerId', 'exists', 'className' => 'Partner', 'attributeName' => 'id', 'allowEmpty' => true],
            ['partnerId', 'default', 'value' => null],

            ['type', 'required'],
            ['type', 'in', 'range' => array_keys(self::$config)],

            ['rooms', 'safe'],
        ];
    }

    public function relations()
    {
        $alias = $this->getTableAlias();

        $relations = [
            'hotel'   => [self::HAS_ONE, 'Hotel', ['id' => 'hotelId'], 'alias' => "{$alias}_hotel"],
            'partner' => [self::HAS_ONE, 'Partner', ['id' => 'partnerId'], 'alias' => "{$alias}_partner"],
            'rooms'   => [self::MANY_MANY, 'HotelRoom', 'hotel_offer_room(offerId, roomId)', 'index' => 'id', 'alias' => "{$alias}_rooms"],
        ];

        foreach (self::$config as $relConfig) {
            $relations[$relConfig['property']] = [
                self::HAS_ONE,
                $relConfig['class'],
                ['offerId' => 'id'],
                'alias' => "{$alias}_{$relConfig['property']}",
            ];
        }

        return $relations;
    }

    public function scopes()
    {
        $alias = $this->getTableAlias();

        $result = [
            'active'      => [
                'condition' => "{$alias}.toDate >= :dateFrom",
                'params'    => [
                    'dateFrom' => date('Y-m-d H:i:s'),
                ],
            ],
            'ordered'     => [
                'order' => "{$alias}.fromDate ASC, {$alias}.toDate ASC",
            ],
            'withDetails' => [
                'with' => [],
            ],
        ];

        foreach (self::$config as $relConfig) {
            $result['withDetails']['with'][] = $relConfig['property'];
        }

        return $result;
    }

    /**
     * @return $this
     */
    public function onlyPromoHotels()
    {
        $this->dbCriteria->mergeWith([
            'with'      => 'hotel',
            'condition' => "t_hotel.promo = 1",
        ]);

        return $this;
    }

    /**
     * @return OfferBonusNightsInfo|OfferEarlyBookingInfo|OfferSpecialSeasonInfo
     */
    public function getDetails()
    {
        if (isset(self::$config[$this->type])) {
            $relCfg = self::$config[$this->type];
            return $this->{"{$relCfg['property']}"};
        }

        return null;
    }

    /**
     * @return OfferBonusNightsInfo|OfferEarlyBookingInfo|OfferSpecialSeasonInfo
     * @throws Exception
     */
    public function setDetails()
    {
        if (isset(self::$config[$this->type])) {
            $relCfg = self::$config[$this->type];
            return $this->{"{$relCfg['property']}"};
        }

        throw new Exception("Unknown details type");
    }

    public function attributeLabels()
    {
        return [
            'fromDate'  => 'Начало периода проживания',
            'toDate'    => 'Конец периода проживания',
            'partnerId' => 'Партнер',
            'roomIds'   => 'Категории номеров',
        ];
    }

    /**
     * @param mixed $fromDate
     */
    public function setFromDate($fromDate)
    {
        $this->fromDate = $this->getDateFormatterEx()
            ->formatDateToMysqlFormat($fromDate);
    }

    /**
     * @return string
     */
    public function getFromDate()
    {
        return $this->fromDate;
    }

    /**
     * @param string $toDate
     */
    public function setToDate($toDate)
    {
        $this->toDate = $this->getDateFormatterEx()
            ->formatDateToMysqlFormat($toDate);
    }

    /**
     * @return String
     */
    public function getToDate()
    {
        return $this->toDate;
    }

    /**
     * @return bool
     */
    public function isActual()
    {
        return $this->toDate > date('Y-m-d 23:59:59');
    }

    /**
     * @return mixed|string
     */
    public function getTypeLabel()
    {
        return $this->getDetails()->getTitle();
    }

    /**
     * @return string
     */
    public function getDetailsDescription()
    {
        if (null === $this->getDetails()) {
            return null;
        }

        switch ($this->type) {
            case self::TYPE_BONUS_NIGHTS:
                return "{$this->getDetails()->requiredNightsCount} + {$this->getDetails()->freeNightsCount}";
            case self::TYPE_EARLY_BOOKING:
                return "{$this->getDetails()->discount}%";
            case self::TYPE_SPECIAL_SEASON:
                $costGroup = $this->hotel->typeCostGroup
                    ? $this->hotel->typeCostGroup
                    : $this->costGroupRegistry->getHotelCostGroup();
                $priceNett = $this->getDetails()->priceNett;

                return "{$priceNett} {$this->getDetails()->currency} → {$costGroup->calculateSell($priceNett)} {$this->getDetails()->currency}";
        }

        return 'Unknown type';
    }

    /**
     * @return string
     */
    public function getDetailsNettDescription()
    {
        if (null === $this->getDetails()) {
            return null;
        }

        switch ($this->type) {
            case self::TYPE_BONUS_NIGHTS:
                return "{$this->getDetails()->requiredNightsCountNett} + {$this->getDetails()->freeNightsCountNett}";
            case self::TYPE_EARLY_BOOKING:
                return "{$this->getDetails()->discountNett}%";
            case self::TYPE_SPECIAL_SEASON:
                $costGroup = $this->hotel->typeCostGroup
                    ? $this->hotel->typeCostGroup
                    : $this->costGroupRegistry->getHotelCostGroup();
                $priceNett = $this->getDetails()->pricePartnerNett;

                return "{$priceNett} {$this->getDetails()->currency} → {$costGroup->calculateSell($priceNett)} {$this->getDetails()->currency}";
        }

        return 'Unknown type';
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        $title = $this->getDetails()->getTitle();
        $info = $this->getDetails()->getInfo();

        return "Специальное предложение «${title}».\n"
            . (empty($info) ? '' : "{$info}. ");
    }

    /**
     * @return DateFormatterEx|IApplicationComponent
     */
    protected function getDateFormatterEx()
    {
        return Yii::app()->getComponent('dateFormatterEx');
    }

    /**
     * @return string[]
     */
    public static function getAvailableTypes()
    {
        $types = [];

        foreach (self::$config as $type => $cfg) {
            $types[$type] = $cfg['label'];
        }

        return $types;
    }

    /**
     * @param string $type
     *
     * @return string[]
     */
    public static function getDetailsConfig($type)
    {
        if (isset(self::$config[$type])) {
            return self::$config[$type];
        }

        return null;
    }

    public function behaviors()
    {
        return [
            'AdvancedArBehavior' => [
                'class' => 'application.models.behaviors.AdvancedArBehavior',
            ],
        ];
    }

    public function setRoomIds($roomIds)
    {
        $this->rooms = $roomIds ?: [];
        $this->roomIds = $roomIds ?: [];
    }

    /**
     * @return array
     */
    public function getRoomIds()
    {
        if (count($this->rooms) > 0) {
            return array_values($this->rooms)[0] instanceof HotelRoom
                ? array_keys($this->rooms)
                : $this->rooms;
        }

        return [];
    }

    /**
     * @param Country $country
     *
     * @return int
     */
    public function countByCountry(Country $country)
    {
        return (int)$this->count([
            'with'      => [
                'hotel',
                'hotel.city',
            ],
            'condition' => 't_city.countryId = :countryId',
            'params'    => [
                'countryId' => $country->id,
            ],
        ]);
    }

    /**
     * @param Hotel $hotel
     *
     * @return int
     */
    public function countByHotel(Hotel $hotel)
    {

        return (int)$this->count([
            'condition' => 't.hotelId = :hotelId',
            'params'    => [
                'hotelId' => $hotel->id,
            ],
        ]);
    }

    /**
     * @param Country $country
     *
     * @return HotelOffer[]|static[]
     */
    public function findAllByCountry(Country $country)
    {
        return $this
            ->withDetails()
            ->findAll([
                'with'      => ['hotel', 'hotel.city'],
                'condition' => 't_city.countryId = :countryId',
                'params'    => [
                    'countryId' => $country->id,
                ],
            ]);
    }

    /**
     * @param Hotel $hotel
     *
     * @return $this
     */
    public function hotel(Hotel $hotel)
    {
        $alias = $this->getTableAlias();

        $criteria = $this->getDbCriteria();
        $criteria->addCondition("{$alias}.hotelId = :hotelId");
        $criteria->params['hotelId'] = $hotel->id;

        $this->setDbCriteria($criteria);

        return $this;
    }

    /**
     * @param string $dateStart
     * @param string $dateEnd
     * @return $this
     */
    public function byDates($dateStart, $dateEnd)
    {
        $alias = $this->getTableAlias();

        $criteria = $this->getDbCriteria();
        $criteria->addCondition("{$alias}.toDate >= :dateFrom");
        $criteria->params['dateFrom'] = date('Y-m-d H:i:s');

        $criteria->addCondition("{$alias}.toDate >= :date_start");
        $criteria->params['date_start'] = $dateStart;

        $criteria->addCondition("{$alias}.fromDate <= :date_end");
        $criteria->params['date_end'] = $dateEnd;

        $this->setDbCriteria($criteria);

        return $this;
    }

    /**
     * @param Hotel $hotel
     * @param bool $onlyActual
     *
     * @return static[]
     */
    public function findAllByHotel(Hotel $hotel, $onlyActual = false)
    {
        if ($onlyActual) {
            $this->active();
        }

        return $this->hotel($hotel)->findAll();
    }

    protected function beforeDelete()
    {
        $ret = parent::beforeDelete();

        if (!$ret) {
            return $ret;
        }

        $roomIds = $this->getRoomIds();
        $this->rooms = [];
        $this->save(false);
        $this->setRoomIds($roomIds);

        return true;
    }

    public function isAvailableNow()
    {
        $now = new DateTime();
        $to = new DateTime($this->getToDate());

        $details = $this->getDetails();
        if ($details->bookingMinDays > 0) {
            $min = clone $now;
            $min = $min->add(DateInterval::createFromDateString("{$details->bookingMinDays} days"));

            return $min <= $to;
        }

        if ($details->getBookingDateStart() && $details->getBookingDateEnd()) {
            $d1 = new DateTime($details->getBookingDateStart());
            $d2 = new DateTime($details->getBookingDateEnd());

            return $now >= $d1 && $now <= $d2;
        }

        return true;
    }

    public function isAvailableForSeason(HotelSeason $season)
    {
        $now = new \DateTime();

        $seasonFrom = new DateTime($season->getFromDate());
        $seasonTo = new DateTime($season->getToDate());

        $offerFrom = max($now, new \DateTime($this->getFromDate()));
        $offerTo = new \DateTime($this->getToDate());

        /** @var DateTime $maxOfferTo */
        $maxOfferTo = min($offerTo, $seasonTo);
        /** @var DateTime $minOfferFrom */
        $minOfferFrom = max($offerFrom, $seasonFrom);

        if ($maxOfferTo->diff($minOfferFrom)->days < $this->getCountOfRequiredNights()) {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function getCountOfRequiredNights()
    {
        $details = $this->getDetails();

        switch ($this->type) {
            case self::TYPE_BONUS_NIGHTS:
                return $details->requiredNightsCount + $details->freeNightsCount;
            case self::TYPE_EARLY_BOOKING:
            case self::TYPE_SPECIAL_SEASON:
                return $details->requiredNightsCount;
        }

        return 1;
    }

    public function isAvailableForRoom(HotelRoom $room)
    {
        if (empty($this->getRoomIds())) {
            return true;
        }

        if (!in_array($room->id, $this->getRoomIds())) {
            return false;
        }

        return true;
    }
}
