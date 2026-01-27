

**QUANTUM ASTROLOGY**

Laravel Migration Architecture

Complete Technical Specification

Version 2.0 | January 2026

Prepared for: Mark @ Quantum Minds United

# **1\. Executive Summary**

This document provides a comprehensive technical specification for migrating Quantum Astrology from its current vanilla PHP implementation to Laravel 12\. The migration will preserve all existing functionality while gaining Laravel's robust ecosystem, improved maintainability, and enterprise-grade features.

## **1.1 Current State Analysis**

The existing Quantum Astrology application (v1.3) consists of:

* 134 commits of active development

* Swiss Ephemeris integration via CLI (swetest)

* Custom authentication system with MySQL/SQLite

* SVG chart generation with zodiac wheels

* REST API endpoints for chart management

* Manual database migrations and validation

## **1.2 Migration Benefits**

| Category | Benefits |
| :---- | :---- |
| Authentication | Laravel Breeze/Fortify: 2FA, email verification, password resets, social auth |
| Database | Eloquent ORM, migrations, seeders, model factories |
| Performance | Redis caching, queue jobs for heavy calculations, Horizon dashboard |
| Testing | Pest PHP with 100% coverage, browser testing, parallel execution |
| API | Sanctum tokens, API Resources, rate limiting, versioning |
| Frontend | Inertia \+ React for SPA experience, or Livewire for reactive components |

# **2\. Directory Structure**

The Laravel application follows a clean, modular architecture with domain-driven design principles.

## **2.1 Root Structure**

quantum-astrology/

├── app/

│   ├── Actions/           \# Single-responsibility action classes

│   ├── Console/           \# Artisan commands

│   ├── Contracts/         \# Interfaces

│   ├── DTOs/              \# Data Transfer Objects

│   ├── Enums/             \# PHP 8.1+ enums

│   ├── Events/            \# Domain events

│   ├── Exceptions/        \# Custom exceptions

│   ├── Http/

│   │   ├── Controllers/

│   │   ├── Middleware/

│   │   ├── Requests/      \# Form requests with validation

│   │   └── Resources/     \# API transformers

│   ├── Jobs/              \# Queued jobs

│   ├── Listeners/         \# Event listeners

│   ├── Models/            \# Eloquent models

│   ├── Notifications/

│   ├── Policies/          \# Authorization policies

│   ├── Providers/

│   ├── Services/          \# Business logic services

│   └── Support/           \# Helpers and utilities

├── config/

├── database/

│   ├── factories/

│   ├── migrations/

│   └── seeders/

├── resources/

│   ├── js/                \# React/TypeScript frontend

│   ├── css/

│   └── views/

├── routes/

├── storage/

│   ├── app/

│   │   ├── charts/        \# Generated chart files

│   │   └── ephemeris/     \# Swiss Ephemeris cache

│   └── logs/

├── tests/

│   ├── Feature/

│   ├── Unit/

│   └── Browser/           \# Pest browser tests

└── vendor/

# **3\. Database Architecture**

The database design uses normalized tables with proper indexing and foreign key constraints.

## **3.1 Entity Relationships**

| Entity | Relationship | Entity | Type |
| :---- | :---- | :---- | :---- |
| User | has many | Chart | 1:N |
| Chart | has many | PlanetPosition | 1:N |
| Chart | has many | HouseCusp | 1:N |
| Chart | has many | Aspect | 1:N |
| Chart | belongs to many | Tag | N:N |
| User | has many | Report | 1:N |

## **3.2 Users Migration**

Schema::create('users', function (Blueprint $table) {

    $table-\>id();

    $table-\>string('name');

    $table-\>string('email')-\>unique();

    $table-\>timestamp('email\_verified\_at')-\>nullable();

    $table-\>string('password');

    $table-\>string('timezone')-\>default('UTC');

    $table-\>json('preferences')-\>nullable();

    $table-\>enum('subscription\_tier', \['free', 'pro', 'premium'\])-\>default('free');

    $table-\>rememberToken();

    $table-\>timestamps();

    $table-\>softDeletes();

});

## **3.3 Charts Migration**

Schema::create('charts', function (Blueprint $table) {

    $table-\>id();

    $table-\>foreignId('user\_id')-\>constrained()-\>cascadeOnDelete();

    $table-\>string('name');

    $table-\>enum('type', \['natal', 'transit', 'synastry', 'composite', 'progressed', 'solar\_return'\]);

    $table-\>dateTime('chart\_datetime');

    $table-\>decimal('latitude', 10, 7);

    $table-\>decimal('longitude', 10, 7);

    $table-\>string('location\_name')-\>nullable();

    $table-\>string('timezone');

    $table-\>enum('house\_system', \['placidus', 'koch', 'equal', 'whole\_sign', 'campanus'\]);

    $table-\>enum('zodiac\_type', \['tropical', 'sidereal'\])-\>default('tropical');

    $table-\>json('settings')-\>nullable();

    $table-\>text('notes')-\>nullable();

    $table-\>boolean('is\_public')-\>default(false);

    $table-\>timestamps();

    $table-\>softDeletes();

    $table-\>index(\['user\_id', 'type'\]);

});

## **3.4 Planet Positions Migration**

Schema::create('planet\_positions', function (Blueprint $table) {

    $table-\>id();

    $table-\>foreignId('chart\_id')-\>constrained()-\>cascadeOnDelete();

    $table-\>string('planet');  // sun, moon, mercury, etc.

    $table-\>decimal('longitude', 10, 6);  // 0-360 degrees

    $table-\>decimal('latitude', 10, 6)-\>nullable();

    $table-\>decimal('distance', 15, 6)-\>nullable();  // AU

    $table-\>decimal('speed', 10, 6)-\>nullable();  // degrees per day

    $table-\>boolean('is\_retrograde')-\>default(false);

    $table-\>unsignedTinyInteger('sign');  // 1-12

    $table-\>unsignedTinyInteger('house')-\>nullable();  // 1-12

    $table-\>decimal('sign\_degree', 5, 2);  // 0-30 within sign

    $table-\>timestamps();

    $table-\>unique(\['chart\_id', 'planet'\]);

});

## **3.5 Aspects Migration**

Schema::create('aspects', function (Blueprint $table) {

    $table-\>id();

    $table-\>foreignId('chart\_id')-\>constrained()-\>cascadeOnDelete();

    $table-\>string('planet1');

    $table-\>string('planet2');

    $table-\>string('aspect\_type');  // conjunction, trine, square, etc.

    $table-\>decimal('orb', 5, 2);  // actual orb in degrees

    $table-\>decimal('exactness', 5, 2);  // 0-100% how close to exact

    $table-\>boolean('is\_applying')-\>default(true);

    $table-\>timestamps();

    $table-\>index(\['chart\_id', 'aspect\_type'\]);

});

# **4\. Eloquent Models**

Models follow Laravel best practices with strict typing, immutable dates, and explicit relationships.

## **4.1 User Model**

\<?php declare(strict\_types=1);

namespace App\\Models;

use App\\Enums\\SubscriptionTier;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;

use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

use Illuminate\\Database\\Eloquent\\SoftDeletes;

use Illuminate\\Foundation\\Auth\\User as Authenticatable;

use Laravel\\Sanctum\\HasApiTokens;

final class User extends Authenticatable

{

    use HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable \= \['name', 'email', 'password', 'timezone', 'preferences', 'subscription\_tier'\];

    protected function casts(): array

    {

        return \[

            'email\_verified\_at' \=\> 'immutable\_datetime',

            'password' \=\> 'hashed',

            'preferences' \=\> 'collection',

            'subscription\_tier' \=\> SubscriptionTier::class,

        \];

    }

    public function charts(): HasMany

    {

        return $this-\>hasMany(Chart::class);

    }

    public function canCreateChart(): bool

    {

        return match ($this-\>subscription\_tier) {

            SubscriptionTier::Free \=\> $this-\>charts()-\>count() \< 5,

            SubscriptionTier::Pro \=\> $this-\>charts()-\>count() \< 50,

            SubscriptionTier::Premium \=\> true,

        };

    }

}

## **4.2 Chart Model**

\<?php declare(strict\_types=1);

namespace App\\Models;

use App\\Enums\\ChartType;

use App\\Enums\\HouseSystem;

use Illuminate\\Database\\Eloquent\\Model;

use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

use Illuminate\\Database\\Eloquent\\SoftDeletes;

final class Chart extends Model

{

    use SoftDeletes;

    protected $fillable \= \[

        'user\_id', 'name', 'type', 'chart\_datetime', 'latitude', 'longitude',

        'location\_name', 'timezone', 'house\_system', 'zodiac\_type', 'settings', 'notes', 'is\_public',

    \];

    protected function casts(): array

    {

        return \[

            'chart\_datetime' \=\> 'immutable\_datetime',

            'latitude' \=\> 'decimal:7',

            'longitude' \=\> 'decimal:7',

            'type' \=\> ChartType::class,

            'house\_system' \=\> HouseSystem::class,

            'settings' \=\> 'collection',

            'is\_public' \=\> 'boolean',

        \];

    }

    public function user(): BelongsTo { return $this-\>belongsTo(User::class); }

    public function planetPositions(): HasMany { return $this-\>hasMany(PlanetPosition::class); }

    public function houseCusps(): HasMany { return $this-\>hasMany(HouseCusp::class); }

    public function aspects(): HasMany { return $this-\>hasMany(Aspect::class); }

}

# **5\. PHP Enums**

PHP 8.1+ enums provide type safety and eliminate magic strings throughout the codebase.

## **5.1 Planet Enum**

\<?php declare(strict\_types=1);

namespace App\\Enums;

enum Planet: string

{

    case Sun \= 'sun';

    case Moon \= 'moon';

    case Mercury \= 'mercury';

    case Venus \= 'venus';

    case Mars \= 'mars';

    case Jupiter \= 'jupiter';

    case Saturn \= 'saturn';

    case Uranus \= 'uranus';

    case Neptune \= 'neptune';

    case Pluto \= 'pluto';

    case NorthNode \= 'north\_node';

    case Chiron \= 'chiron';

    case Ascendant \= 'ascendant';

    case Midheaven \= 'midheaven';

    public function label(): string

    {

        return match ($this) {

            self::Sun \=\> '☉ Sun',

            self::Moon \=\> '☽ Moon',

            self::Mercury \=\> '☿ Mercury',

            self::Venus \=\> '♀ Venus',

            self::Mars \=\> '♂ Mars',

            self::Jupiter \=\> '♃ Jupiter',

            self::Saturn \=\> '♄ Saturn',

            self::Uranus \=\> '♅ Uranus',

            self::Neptune \=\> '♆ Neptune',

            self::Pluto \=\> '♇ Pluto',

            self::NorthNode \=\> '☊ North Node',

            self::Chiron \=\> '⚷ Chiron',

            self::Ascendant \=\> 'ASC',

            self::Midheaven \=\> 'MC',

        };

    }

    public function sweCode(): int

    {

        return match ($this) {

            self::Sun \=\> 0, self::Moon \=\> 1, self::Mercury \=\> 2,

            self::Venus \=\> 3, self::Mars \=\> 4, self::Jupiter \=\> 5,

            self::Saturn \=\> 6, self::Uranus \=\> 7, self::Neptune \=\> 8,

            self::Pluto \=\> 9, self::NorthNode \=\> 10, self::Chiron \=\> 15,

            default \=\> \-1,

        };

    }

}

## **5.2 Zodiac Sign Enum**

\<?php declare(strict\_types=1);

namespace App\\Enums;

enum ZodiacSign: int

{

    case Aries \= 1;

    case Taurus \= 2;

    case Gemini \= 3;

    case Cancer \= 4;

    case Leo \= 5;

    case Virgo \= 6;

    case Libra \= 7;

    case Scorpio \= 8;

    case Sagittarius \= 9;

    case Capricorn \= 10;

    case Aquarius \= 11;

    case Pisces \= 12;

    public function symbol(): string

    {

        return match ($this) {

            self::Aries \=\> '♈', self::Taurus \=\> '♉', self::Gemini \=\> '♊',

            self::Cancer \=\> '♋', self::Leo \=\> '♌', self::Virgo \=\> '♍',

            self::Libra \=\> '♎', self::Scorpio \=\> '♏', self::Sagittarius \=\> '♐',

            self::Capricorn \=\> '♑', self::Aquarius \=\> '♒', self::Pisces \=\> '♓',

        };

    }

    public function element(): Element

    {

        return match ($this) {

            self::Aries, self::Leo, self::Sagittarius \=\> Element::Fire,

            self::Taurus, self::Virgo, self::Capricorn \=\> Element::Earth,

            self::Gemini, self::Libra, self::Aquarius \=\> Element::Air,

            self::Cancer, self::Scorpio, self::Pisces \=\> Element::Water,

        };

    }

    public static function fromLongitude(float $longitude): self

    {

        $normalized \= fmod($longitude, 360);

        if ($normalized \< 0\) $normalized \+= 360;

        return self::from((int) floor($normalized / 30\) \+ 1);

    }

}

## **5.3 Aspect Type Enum**

\<?php declare(strict\_types=1);

namespace App\\Enums;

enum AspectType: string

{

    case Conjunction \= 'conjunction';

    case Opposition \= 'opposition';

    case Trine \= 'trine';

    case Square \= 'square';

    case Sextile \= 'sextile';

    case Quincunx \= 'quincunx';

    public function degrees(): float

    {

        return match ($this) {

            self::Conjunction \=\> 0.0,

            self::Sextile \=\> 60.0,

            self::Square \=\> 90.0,

            self::Trine \=\> 120.0,

            self::Quincunx \=\> 150.0,

            self::Opposition \=\> 180.0,

        };

    }

    public function defaultOrb(): float

    {

        return match ($this) {

            self::Conjunction, self::Opposition \=\> 8.0,

            self::Trine, self::Square \=\> 7.0,

            self::Sextile \=\> 5.0,

            self::Quincunx \=\> 3.0,

        };

    }

}

# **6\. Service Layer**

Services encapsulate business logic and external integrations, keeping controllers thin.

## **6.1 Swiss Ephemeris Service**

\<?php declare(strict\_types=1);

namespace App\\Services;

use App\\Contracts\\EphemerisInterface;

use App\\DTOs\\ChartData;

use App\\DTOs\\PlanetPositionData;

use App\\Enums\\Planet;

use App\\Exceptions\\EphemerisException;

use Illuminate\\Support\\Collection;

use Illuminate\\Support\\Facades\\Cache;

use Illuminate\\Support\\Facades\\Process;

final class SwissEphemerisService implements EphemerisInterface

{

    private readonly string $swetestPath;

    private readonly string $ephemerisPath;

    public function \_\_construct()

    {

        $this-\>swetestPath \= config('astrology.swetest\_path');

        $this-\>ephemerisPath \= config('astrology.ephemeris\_path');

    }

    /\*\* @return Collection\<int, PlanetPositionData\> \*/

    public function calculatePlanetPositions(ChartData $chart): Collection

    {

        $cacheKey \= $this-\>buildCacheKey('planets', $chart);

        return Cache::remember($cacheKey, now()-\>addDays(30), function () use ($chart) {

            $positions \= collect();

            foreach (Planet::cases() as $planet) {

                if ($planet \=== Planet::Ascendant || $planet \=== Planet::Midheaven) continue;

                $result \= $this-\>executeSwetestPlanet($planet, $chart);

                $positions-\>push($this-\>parsePlanetResult($planet, $result));

            }

            return $positions;

        });

    }

    private function executeSwetestPlanet(Planet $planet, ChartData $chart): string

    {

        $result \= Process::timeout(30)-\>run(\[

            $this-\>swetestPath,

            '-edir' . $this-\>ephemerisPath,

            '-b' . $chart-\>datetime-\>format('d.m.Y'),

            '-ut' . $chart-\>datetime-\>format('H:i:s'),

            '-p' . $planet-\>sweCode(),

            '-fPZlbrs',

            '-head',

        \]);

        if ($result-\>failed()) {

            throw new EphemerisException("Swetest failed: {$result-\>errorOutput()}");

        }

        return $result-\>output();

    }

    private function buildCacheKey(string $type, ChartData $chart): string

    {

        return sprintf('ephemeris:%s:%s:%s:%s:%s',

            $type, $chart-\>datetime-\>format('Y-m-d-H-i'),

            $chart-\>latitude, $chart-\>longitude, $chart-\>houseSystem-\>value);

    }

}

# **7\. Action Classes**

Single-responsibility action classes handle discrete operations, improving testability.

## **7.1 Create Chart Action**

\<?php declare(strict\_types=1);

namespace App\\Actions\\Charts;

use App\\Contracts\\EphemerisInterface;

use App\\DTOs\\ChartData;

use App\\Events\\ChartCreated;

use App\\Models\\Chart;

use App\\Models\\User;

use Illuminate\\Support\\Facades\\DB;

final readonly class CreateChartAction

{

    public function \_\_construct(

        private EphemerisInterface $ephemeris,

        private CalculateAspectsAction $calculateAspects,

    ) {}

    public function execute(User $user, ChartData $data): Chart

    {

        return DB::transaction(function () use ($user, $data) {

            // Create base chart

            $chart \= $user-\>charts()-\>create(\[

                'name' \=\> $data-\>name,

                'type' \=\> $data-\>type,

                'chart\_datetime' \=\> $data-\>datetime,

                'latitude' \=\> $data-\>latitude,

                'longitude' \=\> $data-\>longitude,

                'location\_name' \=\> $data-\>locationName,

                'timezone' \=\> $data-\>timezone,

                'house\_system' \=\> $data-\>houseSystem,

                'zodiac\_type' \=\> $data-\>zodiacType,

            \]);

            // Calculate and store planet positions

            $positions \= $this-\>ephemeris-\>calculatePlanetPositions($data);

            foreach ($positions as $position) {

                $chart-\>planetPositions()-\>create(\[...\]);

            }

            // Calculate and store house cusps

            $cusps \= $this-\>ephemeris-\>calculateHouseCusps($data);

            foreach ($cusps as $cusp) {

                $chart-\>houseCusps()-\>create(\[...\]);

            }

            // Calculate aspects

            $aspects \= $this-\>calculateAspects-\>execute($chart);

            $chart-\>aspects()-\>createMany($aspects-\>toArray());

            event(new ChartCreated($chart));

            return $chart-\>fresh(\['planetPositions', 'houseCusps', 'aspects'\]);

        });

    }

}

# **8\. API Layer**

RESTful API with JSON:API compliant resources, authentication, and rate limiting.

## **8.1 Routes (routes/api.php)**

\<?php

use App\\Http\\Controllers\\Api\\ChartController;

use App\\Http\\Controllers\\Api\\TransitController;

use App\\Http\\Controllers\\Api\\ReportController;

use Illuminate\\Support\\Facades\\Route;

Route::middleware(\['auth:sanctum', 'throttle:api'\])-\>group(function () {

    // Charts

    Route::apiResource('charts', ChartController::class);

    Route::get('charts/{chart}/svg', \[ChartController::class, 'svg'\])-\>name('charts.svg');

    Route::get('charts/{chart}/pdf', \[ChartController::class, 'pdf'\])-\>name('charts.pdf');

    // Transits

    Route::get('charts/{chart}/transits', \[TransitController::class, 'index'\]);

    Route::get('transits/current', \[TransitController::class, 'current'\]);

    // Reports

    Route::post('charts/{chart}/reports', \[ReportController::class, 'generate'\]);

    Route::get('reports/{report}/download', \[ReportController::class, 'download'\]);

});

## **8.2 Chart Controller**

\<?php declare(strict\_types=1);

namespace App\\Http\\Controllers\\Api;

use App\\Actions\\Charts\\CreateChartAction;

use App\\DTOs\\ChartData;

use App\\Http\\Requests\\StoreChartRequest;

use App\\Http\\Resources\\ChartCollection;

use App\\Http\\Resources\\ChartResource;

use App\\Models\\Chart;

use Illuminate\\Http\\Request;

final class ChartController extends Controller

{

    public function index(Request $request): ChartCollection

    {

        $charts \= $request-\>user()

            \-\>charts()

            \-\>with(\['planetPositions', 'aspects'\])

            \-\>when($request-\>type, fn ($q, $type) \=\> $q-\>where('type', $type))

            \-\>latest()

            \-\>paginate($request-\>input('per\_page', 15));

        return new ChartCollection($charts);

    }

    public function store(StoreChartRequest $request, CreateChartAction $action): ChartResource

    {

        $data \= ChartData::fromRequest($request-\>validated());

        $chart \= $action-\>execute($request-\>user(), $data);

        return ChartResource::make($chart)-\>response()-\>setStatusCode(201);

    }

    public function show(Chart $chart): ChartResource

    {

        $this-\>authorize('view', $chart);

        return ChartResource::make($chart-\>load(\['planetPositions', 'houseCusps', 'aspects'\]));

    }

    public function destroy(Chart $chart): JsonResponse

    {

        $this-\>authorize('delete', $chart);

        $chart-\>delete();

        return response()-\>json(null, 204);

    }

}

# **9\. Frontend Architecture**

React \+ TypeScript with Inertia.js for seamless SPA experience.

## **9.1 TypeScript Interfaces**

// resources/js/types/astrology.ts

export interface Chart {

  id: number;

  name: string;

  type: ChartType;

  datetime: string;

  location: Location;

  settings: ChartSettings;

  planets: PlanetPosition\[\];

  houses: HouseCusp\[\];

  aspects: Aspect\[\];

}

export interface PlanetPosition {

  planet: Planet;

  longitude: number;

  sign: ZodiacSign;

  signDegree: number;

  house: number;

  isRetrograde: boolean;

}

export interface Aspect {

  planet1: Planet;

  planet2: Planet;

  type: AspectType;

  orb: number;

  exactness: number;

  isApplying: boolean;

}

export type ChartType \= 'natal' | 'transit' | 'synastry' | 'composite' | 'progressed';

export type AspectType \= 'conjunction' | 'opposition' | 'trine' | 'square' | 'sextile';

## **9.2 Chart Wheel Component**

// resources/js/Components/ChartWheel.tsx

import { useEffect, useRef } from 'react';

import { Chart, PlanetPosition } from '@/types/astrology';

interface Props {

  chart: Chart;

  size?: number;

  showAspects?: boolean;

}

export function ChartWheel({ chart, size \= 600, showAspects \= true }: Props) {

  const svgRef \= useRef\<SVGSVGElement\>(null);

  const center \= size / 2;

  const zodiacRadius \= size \* 0.45;

  const planetRadius \= size \* 0.35;

  const calculatePosition \= (longitude: number, radius: number) \=\> {

    const angle \= (longitude \- 90\) \* (Math.PI / 180);

    return {

      x: center \+ radius \* Math.cos(angle),

      y: center \+ radius \* Math.sin(angle),

    };

  };

  return (

    \<svg ref={svgRef} width={size} height={size} className="chart-wheel"\>

      {/\* Zodiac ring \*/}

      \<ZodiacRing center={center} radius={zodiacRadius} /\>

      

      {/\* House cusps \*/}

      {chart.houses.map(house \=\> (

        \<HouseCuspLine key={house.number} house={house} center={center} radius={zodiacRadius} /\>

      ))}

      

      {/\* Planet glyphs \*/}

      {chart.planets.map(planet \=\> (

        \<PlanetGlyph key={planet.planet} position={planet} center={center} radius={planetRadius} /\>

      ))}

      

      {/\* Aspect lines \*/}

      {showAspects && chart.aspects.map((aspect, i) \=\> (

        \<AspectLine key={i} aspect={aspect} planets={chart.planets} center={center} radius={planetRadius \* 0.8} /\>

      ))}

    \</svg\>

  );

}

# **10\. Testing Strategy**

Comprehensive test suite with Pest PHP targeting 100% code coverage.

## **10.1 Unit Tests**

// tests/Unit/Services/SwissEphemerisServiceTest.php

use App\\DTOs\\ChartData;

use App\\Enums\\ChartType;

use App\\Enums\\HouseSystem;

use App\\Enums\\Planet;

use App\\Services\\SwissEphemerisService;

use Carbon\\CarbonImmutable;

beforeEach(function () {

    $this-\>service \= new SwissEphemerisService();

    $this-\>chartData \= new ChartData(

        name: 'Test Chart',

        type: ChartType::Natal,

        datetime: CarbonImmutable::parse('2000-01-01 12:00:00', 'UTC'),

        latitude: 41.8781,

        longitude: \-87.6298,

        timezone: 'America/Chicago',

        houseSystem: HouseSystem::Placidus,

    );

});

it('calculates planet positions correctly', function () {

    $positions \= $this-\>service-\>calculatePlanetPositions($this-\>chartData);

    expect($positions)-\>toHaveCount(12);

    expect($positions-\>firstWhere('planet', Planet::Sun))-\>not-\>toBeNull();

});

it('caches ephemeris calculations', function () {

    $this-\>service-\>calculatePlanetPositions($this-\>chartData);

    $this-\>service-\>calculatePlanetPositions($this-\>chartData);

    // Process should only be called once due to caching

    Process::assertRanTimes(1);

});

## **10.2 Feature Tests**

// tests/Feature/Api/ChartTest.php

use App\\Models\\Chart;

use App\\Models\\User;

beforeEach(function () {

    $this-\>user \= User::factory()-\>create();

});

it('creates a natal chart', function () {

    $response \= $this-\>actingAs($this-\>user)

        \-\>postJson('/api/charts', \[

            'name' \=\> 'My Birth Chart',

            'type' \=\> 'natal',

            'datetime' \=\> '1990-06-15T14:30:00',

            'latitude' \=\> 41.8781,

            'longitude' \=\> \-87.6298,

            'timezone' \=\> 'America/Chicago',

            'house\_system' \=\> 'placidus',

        \]);

    $response-\>assertStatus(201)

        \-\>assertJsonPath('data.attributes.name', 'My Birth Chart')

        \-\>assertJsonStructure(\[

            'data' \=\> \[

                'id', 'type', 'attributes', 'relationships' \=\> \['planets', 'houses', 'aspects'\],

            \],

        \]);

});

it('enforces chart limits for free users', function () {

    Chart::factory()-\>count(5)-\>for($this-\>user)-\>create();

    $response \= $this-\>actingAs($this-\>user)

        \-\>postJson('/api/charts', \[...\]);

    $response-\>assertStatus(403);

});

# **11\. Configuration**

Application configuration with environment-specific settings.

## **11.1 Astrology Config (config/astrology.php)**

\<?php

return \[

    /\*

    |--------------------------------------------------------------------------

    | Swiss Ephemeris Configuration

    |--------------------------------------------------------------------------

    \*/

    'swetest\_path' \=\> env('SWETEST\_PATH', '/usr/local/bin/swetest'),

    'ephemeris\_path' \=\> env('EPHEMERIS\_PATH', storage\_path('app/ephemeris')),

    /\*

    |--------------------------------------------------------------------------

    | Default Chart Settings

    |--------------------------------------------------------------------------

    \*/

    'defaults' \=\> \[

        'house\_system' \=\> 'placidus',

        'zodiac\_type' \=\> 'tropical',

        'aspects' \=\> \['conjunction', 'opposition', 'trine', 'square', 'sextile'\],

    \],

    /\*

    |--------------------------------------------------------------------------

    | Orb Configuration

    |--------------------------------------------------------------------------

    \*/

    'orbs' \=\> \[

        'conjunction' \=\> 8.0,

        'opposition' \=\> 8.0,

        'trine' \=\> 7.0,

        'square' \=\> 7.0,

        'sextile' \=\> 5.0,

        'quincunx' \=\> 3.0,

    \],

    /\*

    |--------------------------------------------------------------------------

    | Branding

    |--------------------------------------------------------------------------

    \*/

    'branding' \=\> \[

        'name' \=\> 'Quantum Astrology',

        'tagline' \=\> 'Powered by Quantum Minds United',

        'logo' \=\> public\_path('images/qmu-logo.svg'),

        'colors' \=\> \[

            'primary' \=\> '\#1a1a2e',

            'secondary' \=\> '\#16213e',

            'accent' \=\> '\#e94560',

        \],

    \],

    /\*

    |--------------------------------------------------------------------------

    | Subscription Limits

    |--------------------------------------------------------------------------

    \*/

    'limits' \=\> \[

        'free' \=\> \['charts' \=\> 5, 'reports' \=\> 1\],

        'pro' \=\> \['charts' \=\> 50, 'reports' \=\> 10\],

        'premium' \=\> \['charts' \=\> \-1, 'reports' \=\> \-1\],  // unlimited

    \],

\];

# **12\. Deployment & DevOps**

Production deployment with Laravel Forge and GitHub Actions CI/CD.

## **12.1 GitHub Actions Workflow**

\# .github/workflows/ci.yml

name: CI

on:

  push:

    branches: \[main, develop\]

  pull\_request:

    branches: \[main\]

jobs:

  test:

    runs-on: ubuntu-latest

    services:

      mysql:

        image: mysql:8.0

        env:

          MYSQL\_DATABASE: testing

          MYSQL\_ROOT\_PASSWORD: password

        ports:

          \- 3306:3306

    steps:

      \- uses: actions/checkout@v4

      \- name: Setup PHP

        uses: shivammathur/setup-php@v2

        with:

          php-version: '8.4'

          extensions: mbstring, pdo\_mysql

          coverage: xdebug

      \- name: Install Dependencies

        run: composer install \--no-progress \--prefer-dist

      \- name: Run Linting

        run: composer lint

      \- name: Run Static Analysis

        run: composer test:types

      \- name: Run Tests

        run: composer test:unit \-- \--coverage-clover coverage.xml

      \- name: Upload Coverage

        uses: codecov/codecov-action@v3

## **12.2 Environment Variables**

\# .env.production

APP\_ENV=production

APP\_DEBUG=false

APP\_URL=https://astrology.quantummindsunited.com

DB\_CONNECTION=mysql

DB\_HOST=127.0.0.1

DB\_PORT=3306

DB\_DATABASE=quantum\_astrology

CACHE\_DRIVER=redis

SESSION\_DRIVER=redis

QUEUE\_CONNECTION=redis

REDIS\_HOST=127.0.0.1

REDIS\_PORT=6379

SWETEST\_PATH=/usr/local/bin/swetest

EPHEMERIS\_PATH=/var/www/storage/ephemeris

# **13\. Migration Plan**

Step-by-step migration strategy from vanilla PHP to Laravel.

## **13.1 Phase 1: Foundation (Week 1\)**

* Create new Laravel 12 project with Inertia \+ React starter kit

* Configure database connections and environment

* Set up authentication with Laravel Breeze

* Create database migrations from existing schema

* Build Eloquent models with relationships

## **13.2 Phase 2: Core Logic (Week 2\)**

* Port Swiss Ephemeris service wrapper

* Implement calculation DTOs and enums

* Create action classes for chart operations

* Build aspect calculation engine

* Set up caching layer for ephemeris data

## **13.3 Phase 3: API & Frontend (Week 3\)**

* Build RESTful API with Sanctum authentication

* Create API Resources for JSON transformation

* Port SVG chart rendering to React component

* Build chart management UI with Inertia

* Implement real-time transit updates

## **13.4 Phase 4: Testing & Polish (Week 4\)**

* Write comprehensive Pest test suite

* Achieve 100% code coverage

* Performance optimization and caching

* Documentation and API specs

* Production deployment setup

## **13.5 Data Migration Script**

\<?php

// database/seeders/MigrateFromLegacySeeder.php

use App\\Models\\Chart;

use App\\Models\\User;

use Illuminate\\Support\\Facades\\DB;

class MigrateFromLegacySeeder extends Seeder

{

    public function run(): void

    {

        $legacy \= DB::connection('legacy');

        // Migrate users

        $legacy-\>table('users')-\>orderBy('id')-\>chunk(100, function ($users) {

            foreach ($users as $user) {

                User::create(\[

                    'id' \=\> $user-\>id,

                    'name' \=\> $user-\>username,

                    'email' \=\> $user-\>email,

                    'password' \=\> $user-\>password\_hash,

                    'created\_at' \=\> $user-\>created\_at,

                \]);

            }

        });

        // Migrate charts

        $legacy-\>table('charts')-\>orderBy('id')-\>chunk(100, function ($charts) {

            foreach ($charts as $chart) {

                $newChart \= Chart::create(\[...\]);

                // Migrate related positions, aspects, etc.

            }

        });

    }

}

# **14\. Summary**

This architecture provides a solid foundation for Quantum Astrology v2.0, leveraging Laravel's ecosystem while preserving all existing functionality.

## **Key Benefits**

* Type-safe codebase with PHP 8.4+ features and strict typing

* Scalable architecture with queued jobs and Redis caching

* Modern frontend with React \+ TypeScript \+ Inertia.js

* Comprehensive testing with 100% code coverage

* Production-ready deployment with CI/CD pipeline

* Clean separation of concerns with Actions, Services, and DTOs

## **Next Steps**

| Priority | Task | Estimated Time |
| :---- | :---- | :---- |
| 1 | Set up Laravel project with Inertia \+ React starter | 2-4 hours |
| 2 | Create database migrations and models | 4-6 hours |
| 3 | Port Swiss Ephemeris service | 6-8 hours |
| 4 | Build API endpoints and resources | 8-12 hours |
| 5 | Create React chart wheel component | 8-12 hours |
| 6 | Write tests and documentation | 8-12 hours |

Total estimated migration time: 2-4 weeks depending on feature completeness.

*— End of Document —*
