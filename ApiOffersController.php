<?php

class ApiOffersController extends BaseController
{
    /** @var OffersService */
    protected $offersService;

    /** @var CostGroupRegistry */
    protected $costGroupRegistry;

    public function filters()
    {
        return [];
    }

    public function init()
    {
        $this->offersService = Yii::app()->getComponent('offersService');
        $this->costGroupRegistry = Yii::app()->getComponent('costGroupRegistry');
    }

    public function actionList(array $hotelIds, $dateFrom, $dateTo)
    {
        $offers = $this->offersService->getOffers(
            ArrayHelper::prepareIntArray($hotelIds),
            DateHelper::parseDatetime($dateFrom),
            DateHelper::parseDatetime($dateTo)
        );

        $this->renderJson($this->prepareOffers($offers));
    }

    /**
     * @param HotelOffer[] $offers
     *
     * @return array
     */
    protected function prepareOffers(array $offers)
    {
        $result = [];

        foreach ($offers as $offer) {
            $result[] = $this->prepareOffer($offer);
        }

        return $result;
    }

    /**
     * @param HotelOffer $offer
     *
     * @return array
     */
    protected function prepareOffer(HotelOffer $offer)
    {
        $result = [
            'id'         => (int)$offer->id,
            'date_start' => $this->prepareDateTime(new \DateTime($offer->getFromDate()), false),
            'date_end'   => $this->prepareDateTime(new \DateTime($offer->getToDate()), false),
            'created_at' => $this->prepareDateTime(new \DateTime($offer->created)),
            'type'       => (string)$offer->type,
            'details'    => $this->prepareOfferDetails($offer),
            'hotel_id'   => (int)$offer->hotelId,
            'rooms'      => $this->prepareOfferRooms($offer->rooms),
        ];

        return $result;
    }

    /**
     * @param HotelRoom[] $rooms
     *
     * @return array
     */
    protected function prepareOfferRooms(array $rooms)
    {
        $result = [];

        foreach ($rooms as $room) {
            $result[] = $this->prepareOfferRoom($room);
        }

        return $result;
    }

    /**
     * @param HotelRoom $room
     *
     * @return int
     */
    protected function prepareOfferRoom(HotelRoom $room)
    {
        return (int)$room->id;
    }

    /**
     * @param DateTime $datetime
     * @param bool $withTime
     *
     * @return string
     */
    protected function prepareDateTime(DateTime $datetime, $withTime = true)
    {
        $format = 'Y-m-d';
        if ($withTime) {
            $format .= ' H:i:s';
        }

        return $datetime->format($format);
    }

    /**
     * @param HotelOffer $offer
     *
     * @return array|null
     */
    protected function prepareOfferDetails(HotelOffer $offer)
    {
        switch ($offer->type) {
            case HotelOffer::TYPE_BONUS_NIGHTS:
                return $this->prepareOfferBonusNightsInfo($offer->bonusNightsInfo);
            case HotelOffer::TYPE_SPECIAL_SEASON:
                return $this->prepareSpecialSeasonInfo($offer);
            case HotelOffer::TYPE_EARLY_BOOKING:
                return $this->prepareOfferEarlyBookingInfo($offer->earlyBookingInfo);
        }

        return null;
    }

    /**
     * @param OfferBonusNightsInfo $details
     *
     * @return array
     */
    protected function prepareOfferBonusNightsInfo(OfferBonusNightsInfo $details)
    {
        return [
            'id'                    => "bonus-nights-{$details->id}",
            'booking_date_start'    => $details->getBookingDateStart()
                ? $this->prepareDateTime(new DateTime($details->getBookingDateStart()), false)
                : null,
            'booking_date_end'      => $details->getBookingDateEnd()
                ? $this->prepareDateTime(new DateTime($details->getBookingDateEnd()), false)
                : null,
            'booking_min_days'      => $details->bookingMinDays > 0 ? (int)$details->bookingMinDays : null,
            'required_nights_count' => (int)$details->requiredNightsCount,
            'free_nights_count'     => (int)$details->freeNightsCount,
            'can_multiple'          => (bool)$details->canMultiple,
            'created_at'            => $this->prepareDateTime(new DateTime($details->created)),
        ];
    }

    /**
     * @param HotelOffer $offer
     *
     * @return array
     */
    protected function prepareSpecialSeasonInfo(HotelOffer $offer)
    {
        $details = $offer->specialSeasonInfo;

        $hotel = Hotel::model()->findByPk($offer->hotelId);
        $costGroup = ($hotel && $hotel->typeCostGroup)
            ? $hotel->typeCostGroup
            : $this->costGroupRegistry->getHotelCostGroup();

        return [
            'id'                    => "special-season-{$details->id}",
            'booking_date_start'    => $details->getBookingDateStart()
                ? $this->prepareDateTime(new DateTime($details->getBookingDateStart()), false)
                : null,
            'booking_date_end'      => $details->getBookingDateEnd()
                ? $this->prepareDateTime(new DateTime($details->getBookingDateEnd()), false)
                : null,
            'booking_min_days'      => $details->bookingMinDays > 0
                ? (int)$details->bookingMinDays
                : null,
            'required_nights_count' => (int)$details->requiredNightsCount,
            'price_nett'            => (float)$details->priceNett,
            'price_sell'            => (float)$costGroup->calculateSell($details->priceNett),
            'currency'              => (string)$details->currency,
            'can_multiple'          => (bool)$details->canMultiple,
            'created_at'            => $this->prepareDateTime(new DateTime($details->created)),
        ];
    }

    /**
     * @param OfferEarlyBookingInfo $details
     *
     * @return array
     */
    protected function prepareOfferEarlyBookingInfo(OfferEarlyBookingInfo $details)
    {
        return [
            'id'                    => "early-booking-{$details->id}",
            'booking_date_start'    => $details->getBookingDateStart()
                ? $this->prepareDateTime(new DateTime($details->getBookingDateStart()), false)
                : null,
            'booking_date_end'      => $details->getBookingDateEnd()
                ? $this->prepareDateTime(new DateTime($details->getBookingDateEnd()), false)
                : null,
            'booking_min_days'      => $details->bookingMinDays > 0
                ? (int)$details->bookingMinDays
                : null,
            'required_nights_count' => (int)$details->requiredNightsCount,
            'discount'              => (int)$details->discount,
            'can_multiple'          => (bool)$details->canMultiple,
            'created_at'            => $this->prepareDateTime(new DateTime($details->created)),
        ];
    }
}
