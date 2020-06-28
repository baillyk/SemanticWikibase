<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\SemanticWikibase\Tests\Translators;

use DataValues\BooleanValue;
use DataValues\DataValue;
use DataValues\DecimalValue;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\Geo\Values\LatLongValue;
use DataValues\MonolingualTextValue;
use DataValues\NumberValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use MediaWiki\Extension\SemanticWikibase\TranslationModel\FixedProperties;
use MediaWiki\Extension\SemanticWikibase\Translators\DataValueTranslator;
use MediaWiki\Extension\SemanticWikibase\Wikibase\TypedDataValue;
use PHPUnit\Framework\TestCase;
use SMW\DataValueFactory;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMWDataItem;
use SMWDIContainer;
use SMWDIGeoCoord;
use SMWDIUri;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;

/**
 * @covers \MediaWiki\Extension\SemanticWikibase\Translators\DataValueTranslator
 */
class DataValueTranslatorTest extends TestCase {

	private const SUBJECT_TITLE = 'TestPage';
	private const SUBJECT_NAMESPACE = NS_MAIN;

	public function testString() {
		$this->assertEquals(
			new \SMWDIBlob( 'foo' ),
			$this->translate( new StringValue( 'foo' ) )
		);
	}

	private function translate( DataValue $dataValue, string $propertyType = '' ): \SMWDataItem {
		return ( new DataValueTranslator(
			DataValueFactory::getInstance(),
			$this->getSubject()
		) )->translate( new TypedDataValue( $propertyType, $dataValue ) );
	}

	private function getSubject(): DIWikiPage {
		return new DIWikiPage( self::SUBJECT_TITLE, self::SUBJECT_NAMESPACE );
	}

	public function testBoolean() {
		$this->assertEquals(
			new \SMWDIBoolean( false ),
			$this->translate( new BooleanValue( false ) )
		);
	}

	public function testNumber() {
		$this->assertEquals(
			new \SMWDINumber( 42 ),
			$this->translate( new NumberValue( 42 ) )
		);
	}

	public function testMonolingualText() {
		/**
		 * @var SMWDIContainer $dataItem
		 */
		$dataItem = $this->translate( new MonolingualTextValue( 'en', 'fluffy bunnies' ) );

		$this->assertSame( SMWDataItem::TYPE_CONTAINER, $dataItem->getDIType() );

		$this->assertEquals(
			[ 'fluffy bunnies' ],
			$dataItem->getSemanticData()->getPropertyValues( new DIProperty( '_TEXT' ) )
		);

		$this->assertEquals(
			[ 'en' ],
			$dataItem->getSemanticData()->getPropertyValues( new DIProperty( '_LCODE' ) )
		);
	}

	public function testGlobeCoordinate() {
		$this->assertEquals(
			SMWDIGeoCoord::newFromLatLong( 1.006, 1.47 ),
			$this->translate( new GlobeCoordinateValue(
				new LatLongValue( 1.006, 1.47 ),
				0.01,
			) )
		);
	}

	public function testTranslateEntityId() {
		$this->assertEquals(
			new DIWikiPage( 'Q1', WB_NS_ITEM ),
			$this->translate( new EntityIdValue( new ItemId( 'Q1' ) ) )
		);

		$this->assertEquals(
			new DIWikiPage( 'P42', WB_NS_PROPERTY ),
			$this->translate( new EntityIdValue( new PropertyId( 'P42' ) ) )
		);
	}

	public function testTranslateDecimalValue() {
		$this->assertEquals(
			new \SMWDINumber( 1337.42 ),
			$this->translate( new DecimalValue( 1337.42 ) )
		);
	}

	public function testTranslateBoundedQuantity() {
		/**
		 * @var SMWDIContainer $container
		 */
		$container = $this->translate( new QuantityValue(
			new DecimalValue( 42 ),
			'mega awesome',
			new DecimalValue( 42.49 ),
			new DecimalValue( 41.51 ),
		) );

		$semanticData = $container->getSemanticData();

		$this->assertEquals(
			[ new \SMWDINumber( 42 ) ],
			$semanticData->getPropertyValues( new DIProperty( FixedProperties::QUANTITY_VALUE ) )
		);

		$this->assertEquals(
			[ new \SMWDINumber( 41.51 ) ],
			$semanticData->getPropertyValues( new DIProperty( FixedProperties::QUANTITY_LOWER_BOUND ) )
		);

		$this->assertEquals(
			[ new \SMWDINumber( 42.49 ) ],
			$semanticData->getPropertyValues( new DIProperty( FixedProperties::QUANTITY_UPPER_BOUND ) )
		);

		$this->assertEquals(
			[ new \SMWDIBlob( 'mega awesome' ) ],
			$semanticData->getPropertyValues( new DIProperty( FixedProperties::QUANTITY_UNIT ) )
		);
	}

	public function testTranslateUnboundedQuantity() {
		/**
		 * @var SMWDIContainer $container
		 */
		$container = $this->translate( new UnboundedQuantityValue(
			new DecimalValue( 42 ),
			'mega awesome'
		) );

		$semanticData = $container->getSemanticData();

		$this->assertEquals(
			[ new \SMWDINumber( 42 ) ],
			$semanticData->getPropertyValues( new DIProperty( FixedProperties::QUANTITY_VALUE ) )
		);

		$this->assertEquals(
			[ new \SMWDIBlob( 'mega awesome' ) ],
			$semanticData->getPropertyValues( new DIProperty( FixedProperties::QUANTITY_UNIT ) )
		);
	}

	public function testTranslateUrlValue() {
		$this->assertEquals(
			new SMWDIUri( 'https', 'www.EntropyWins.wtf/mediawiki', 'hello', 'there' ),
			$this->translate( new StringValue( 'https://www.EntropyWins.wtf/mediawiki?hello#there' ), 'url' )
		);
	}

	public function testTranslateGregorianTimeValue() {
		$this->assertEquals(
			new \SMWDITime(
				\SMWDITime::CM_GREGORIAN,
				2020,
				06,
				27,
				19,
				21,
				42
			),
			$this->translate(
				new TimeValue(
					'+2020-06-27T19:21:42Z',
					0,
					0,
					0,
					11,
					'http://www.wikidata.org/entity/Q1985727'
				),
				'time'
			)
		);
	}

	public function testTranslateJulianTimeValue() {
		$this->assertEquals(
			new \SMWDITime(
				\SMWDITime::CM_JULIAN,
				2020,
				06,
				27,
				0,
				0,
				0
			),
			$this->translate(
				new TimeValue(
					'+2020-06-27T00:00:00Z',
					0,
					10,
					20,
					11,
					'http://www.wikidata.org/entity/Q1985786'
				),
				'time'
			)
		);
	}

	public function testTranslateExternalId() {
		$this->assertEquals(
			new \SMWDIBlob( '978-84-339-1247-3' ),
			$this->translate( new StringValue( '978-84-339-1247-3' ), 'external-id' )
		);
	}

}
