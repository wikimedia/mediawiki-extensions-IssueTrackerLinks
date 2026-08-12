<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Util;

use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use OOUI\ButtonWidget;
use OOUI\FieldLayout;
use OOUI\HorizontalLayout;
use OOUI\HtmlSnippet;
use OOUI\LabelWidget;
use OOUI\PanelLayout;
use OOUI\Widget;

/**
 * Trait to be used to format HTML output of different issue types uniformly.
 */
trait HtmlFormatterTrait {

	public function getDescriptionWidget( string $description, bool $isHtml = true ): PanelLayout {
		$header = new LabelWidget( [
			'label' => Message::newFromKey( 'issuetrackerlinks-issue-entity-description' )->text(),
			'classes' => [ 'issuetrackerlinks-issue-entity-description-header' ],
		] );

		$descriptionLabel = new LabelWidget( [
			'label' => $isHtml ? new HtmlSnippet( $description ) : $description,
			'classes' => [ 'issuetrackerlinks-issue-entity-description' ],
		] );

		$panel = new PanelLayout( [
			'classes' => [ 'issuetrackerlinks-issue-entity-description-panel' ],
			'expanded' => false,
			'padded' => false,
		] );
		$panel->appendContent( $header, $descriptionLabel );
		return $panel;
	}

	/**
	 * @param array $data
	 * @param array $labels
	 * @param array $icons
	 * @param int $maxPerColumn
	 * @param bool $showEmpty
	 * @return FieldLayout[]
	 */
	public function getKeyValueLayouts(
		array $data, array $labels = [], array $icons = [], int $maxPerColumn = 5, bool $showEmpty = true
	): array {
		$layouts = [];
		foreach ( $data as $key => $value ) {
			if ( !$showEmpty && !$value ) {
				continue;
			}
			$label = $labels[$key] ?? $key;
			$valueWidget = $value instanceof Widget ? $value : new LabelWidget( [ 'label' => $value ] );
			$layouts[] = $this->getFieldLayout( $label, $valueWidget, $icons[$key] ?? '' );
		}

		if ( $maxPerColumn < count( $layouts ) ) {
			$layouts = array_chunk( $layouts, $maxPerColumn );
			return [ new HorizontalLayout( [
				'items' => array_map( function ( $row ) {
					$panel = $this->getPanel( ...$row );
					$panel->addClasses( [ 'inline' ] );
					return $panel;
				}, $layouts ),
			] ) ];
		}

		return $layouts;
	}

	public function getColoredValue( string $value, string $colorCode ): LabelWidget {
		$label = new LabelWidget( [
			'label' => $value,
		] );
		$label->setAttributes( [ 'style' => 'color: ' . $colorCode ] );
		return $label;
	}

	/**
	 * @param string $name
	 * @param string $avatar
	 * @param string $url
	 * @return HtmlSnippet
	 * @throws \OOUI\Exception
	 */
	public function getUserHtmlSnippet( string $name, string $avatar = '', string $url = '' ) {
		$items = [];
		if ( $avatar ) {
			$items[] = new HtmlSnippet(
				'<img src="' . htmlspecialchars( $avatar ) . '" alt="' .
				htmlspecialchars( $name ) .
				'" style="width: 20px; height: 20px; border-radius: 50%; margin-right: 5px;">'
			);
		}
		if ( $url ) {
			$items[] = ( new ButtonWidget( [ 'href' => $url, 'label' => $name, 'framed' => false ] ) )->toString();
		} else {
			$items[] = ( new LabelWidget( [ 'label' => $name ] ) )->toString();
		}

		return new HtmlSnippet( Html::rawElement( 'div', [
			'style' => 'display: flex; align-items: center;',
		], implode( ' ', $items ) ) );
	}

	public function getColoredItem( string $value, string $colorCode = '' ): LabelWidget {
		$label = new LabelWidget( [
			'label' => $value,
		] );
		if ( !$colorCode ) {
			return $label;
		}
		$label->prependContent(
			new HtmlSnippet(
				'<span class="issuetrackerlinks-colored-badge" style="background-color: ' . $colorCode . '"></span>'
			)
		);
		return $label;
	}

	/**
	 * @param string $date
	 * @param string $givenFormat
	 * @param string $outFormat
	 * @return string
	 */
	public function formatDate( string $date, string $givenFormat, string $outFormat = 'Y-m-d H:i:s' ): string {
		return date( $outFormat, strtotime( $date ) );
	}

	public function getFieldLayout( string $label, Widget $value, string $icon = '' ): FieldLayout {
		return new FieldLayoutWithIcon( $value, [
			'label' => $label,
			'align' => 'left',
			'icon' => $icon,
			'classes' => [ 'issuetrackerlinks-issue-entity-field' ],
		] );
	}

	/**
	 * @param Widget ...$fields
	 * @return PanelLayout
	 */
	public function getPanel( ...$fields ): PanelLayout {
		$panel = new PanelLayout( [
			'classes' => [ 'issuetrackerlinks-issue-entity-panel' ],
			'expanded' => false,
			'padded' => false,
		] );
		$panel->appendContent( $fields );
		return $panel;
	}
}
