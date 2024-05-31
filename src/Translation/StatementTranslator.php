<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\SemanticWikibase\Translation;

use MediaWiki\Extension\SemanticWikibase\Wikibase\TypedDataValue;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use SMW\DIWikiPage;
use SMWDataItem;
use SMW\Subobject;
use SMW\DataValueFactory;
use SMW\DIProperty;
use MWNamespace;

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

	public function statementToSubobjects( Statement $statement, DIWikiPage $subject , $statementNr): array {
		$result = [];
		$mainSnak = $statement->getMainSnak();
		$qualifiers = $statement->getQualifiers();
		// create a smw semantic subobject for the statement
		$statementObj = new Subobject($subject->getTitle());
		$statementObj->setEmptyContainerForId("statement");
		$statementObj->addDataValue( DataValueFactory::getInstance()->newDataValueByText('subObjectType', 'statement', false, $subject) );
		$statementObj->addDataValue( DataValueFactory::getInstance()->newDataValueByText('property', MWNamespace::getCanonicalName(WB_NS_PROPERTY).":".$mainSnak->getPropertyId()->getLocalPart(), false, $subject) );
		
		
		$mainSnakDI = $this->snakToDataItem($mainSnak, $statement, $subject);
		$statementObj->addDataValue( DataValueFactory::getInstance()->newDataValueByText('value', StatementTranslator::valueDIToString($mainSnakDI), false, $subject) );
		$result[] = $statementObj;
		$qNr = 1;
		foreach( $qualifiers as $actQualifier){
			$qualifierId = "statement".$statementNr."-qualifier".$qNr;
			$qualifierObj = new Subobject($subject->getTitle());
			$qualifierObj->setEmptyContainerForId($qualifierId);
			$qualifierObj->addDataValue( DataValueFactory::getInstance()->newDataValueByText('subObjectType', 'qualifier', false, $subject) );
			$qualifierObj->addDataValue( DataValueFactory::getInstance()->newDataValueByText('property', MWNamespace::getCanonicalName(WB_NS_PROPERTY).":".$actQualifier->getPropertyId()->getLocalPart(), false, $subject) );
			$qualifierDI = $this->snakToDataItem($actQualifier, $statement, $subject);
			$qualifierObj->addDataValue( DataValueFactory::getInstance()->newDataValueByText('value', StatementTranslator::valueDIToString($qualifierDI), false, $subject) );

			// set reference in statement obj
			$qualifierPage = $subject->getTitle()."#".$qualifierId;
			$statementObj->addDataValue( DataValueFactory::getInstance()->newDataValueByText('qualifier', $qualifierPage, false, $subject) );
			$qNr++;
			$result[] = $qualifierObj;
		}

		return $result;
	
	}

	private static function valueDIToString($di) : string {
		if( $di instanceof DIWikiPage ) {
			return $di->getTitle()."";
		}
		return $di."";

	}

}

