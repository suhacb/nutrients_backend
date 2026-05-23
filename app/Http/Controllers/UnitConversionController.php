<?php

namespace App\Http\Controllers;

use App\Exceptions\UnitConversionException;
use App\Http\Requests\UnitConversionRequest;
use App\Http\Resources\UnitResource;
use App\Models\Nutrient;
use App\Models\Unit;
use App\Services\Conversion\UnitConversionServiceContract;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class UnitConversionController extends Controller
{
    #[OA\Post(
        path: '/api/units/convert',
        summary: 'Convert a value between units of the same type',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['value', 'from_unit_id'],
                properties: [
                    new OA\Property(property: 'value',        type: 'number',  example: 100),
                    new OA\Property(property: 'from_unit_id', type: 'integer', example: 2),
                    new OA\Property(property: 'to_unit_id',   type: 'integer', example: 7,    nullable: true, description: 'Required unless from_unit is IU'),
                    new OA\Property(property: 'nutrient_id',  type: 'integer', example: 5,    nullable: true, description: 'Required when from_unit is IU'),
                ]
            )
        ),
        tags: ['Units'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Converted value with unit context',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'value',       type: 'number',  example: 3.5274),
                        new OA\Property(property: 'from_unit',   ref: '#/components/schemas/Unit'),
                        new OA\Property(property: 'to_unit',     ref: '#/components/schemas/Unit'),
                        new OA\Property(property: 'nutrient_id', type: 'integer', nullable: true, example: null),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Incompatible units, missing factor, or validation error'),
        ]
    )]
    public function convert(UnitConversionRequest $request, UnitConversionServiceContract $service): JsonResponse
    {
        $fromUnit = Unit::find($request->integer('from_unit_id'));

        try {
            if ($fromUnit->abbreviation === 'IU') {
                $nutrient = Nutrient::with('canonicalUnit')->findOrFail($request->integer('nutrient_id'));
                $result   = $service->convertFromIU($request->float('value'), $nutrient);

                return response()->json([
                    'value'       => round($result->value, 4),
                    'from_unit'   => new UnitResource($fromUnit),
                    'to_unit'     => new UnitResource($result->unit),
                    'nutrient_id' => $nutrient->id,
                ]);
            }

            $toUnit    = Unit::find($request->integer('to_unit_id'));
            $converted = $service->convert($request->float('value'), $fromUnit, $toUnit);

            return response()->json([
                'value'     => round($converted, 4),
                'from_unit' => new UnitResource($fromUnit),
                'to_unit'   => new UnitResource($toUnit),
            ]);
        } catch (UnitConversionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
