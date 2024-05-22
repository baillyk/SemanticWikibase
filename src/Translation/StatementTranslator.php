<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\SemanticWikibase\Translation;

use MediaWiki\Extension\SemanticWikibase\Wikibase\TypedDataValue;
use SMW\DIWikiPage;
use SMWDataItem;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;

class StatementTranslator {

	private DataValueTranslator $dataValueTranslator;
	private ContainerValueTranslator $containerValueTranslator;
	private PropertyDataTypeLookup $propertyTypeLookup;

	public function __construct( DataValueTranslator $dataValueTranslator, ContainerValueTranslator $containerValueTranslator, PropertyDataTypeLookup $propertyTypeLookup ) {
		$this->propertyTypeLookup = $propertyTypeLookup;
		$this->containerValueTranslator = $containerValueTranslator;
		$this->dataValueTranslator = $dataValueTranslator;
	}

	public function statementToDataItem( Statement $statement, DIWikiPage $subject ): ?SMWDataItem {
		$mainSnak = $statement->getMainSnak();
		return $this->snakToDataItem($mainSnak, $statement, $subject);
	}

	public function statementToQualifiersDataItemList( Statement $statement, DIWikiPage $subject ): array {
		$qualifiers = $statement->getQualifiers();
		$result = [];
		foreach( $qualifiers as $actQualifier) {
            $result[] = $this->snakToDataItem($actQualifier, $statement, $subject);
		} 

		return $result;
	}

	public function snakToDataItem( PropertyValueSnak $snak, Statement $statement, DIWikiPage $subject ): ?SMWDataItem {
		if ( !( $snak instanceof PropertyValueSnak ) ) {
			return null;
		}

		if ( $this->containerValueTranslator->supportsStatement( $statement ) ) {
			return $this->containerValueTranslator->statementToDataItem( $statement, $subject );
		}

		return $this->snakWithSimpleDataValueToDataItem( $snak );
	}

	private function snakWithSimpleDataValueToDataItem( PropertyValueSnak $snak ): SMWDataItem {
		return $this->dataValueTranslator->translate(
			new TypedDataValue(
				$this->propertyTypeLookup->getDataTypeIdForProperty( $snak->getPropertyId() ),
				$snak->getDataValue()
			)
		);
	}

}
