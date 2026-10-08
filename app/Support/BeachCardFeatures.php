<?php

namespace App\Support;

use App\Models\Beach;

/**
 * Card icons (QA #45): beach type, family friendly and parking.
 *
 * Same rules as the beach page badges and PostGISService::loadCardFeatures():
 * family = user type `family`; parking = infrastructure `parking` or a parking
 * capacity other than none/private.
 */
class BeachCardFeatures
{
    public const NO_PARKING_CAPACITY = ['0', 'private_parking_only'];

    /** @return array{beach_type: ?array{code: string, name: string}, family: bool, parking: bool} */
    public static function fromModel(Beach $beach, string $locale): array
    {
        $type = $beach->beachType;
        $typeName = null;
        if ($type) {
            $typeName = $type->translations->firstWhere('locale', $locale)?->name
                ?? $type->translations->firstWhere('locale', 'en')?->name
                ?? $type->code;
        }

        $capacity = $beach->parkingCapacity?->code;

        return [
            'beach_type' => $type ? ['code' => $type->code, 'name' => $typeName] : null,
            'family' => $beach->userTypes->contains('code', 'family'),
            'parking' => $beach->locationInfrastructures->contains('code', 'parking')
                || ($capacity !== null && ! in_array($capacity, self::NO_PARKING_CAPACITY, true)),
        ];
    }

    /** Relations fromModel() reads; eager-load them with the beaches. */
    public const RELATIONS = ['beachType.translations', 'userTypes', 'locationInfrastructures', 'parkingCapacity'];
}
