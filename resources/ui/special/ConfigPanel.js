const DataProvidersGrid = require( './DataProvidersGrid.js' );
const IssueLinksGrid = require( './IssueLinksGrid.js' );

const ConfigPanel = function () {
	ConfigPanel.parent.call( this, {
		expanded: false,
		framed: false
	} );

	this.makeTabs();
	if ( location.hash ) {
		this.setTabPanel( location.hash.replace( '#', '' ) );
	}
};

OO.inheritClass( ConfigPanel, OO.ui.IndexLayout );

ConfigPanel.prototype.makeTabs = function () {
	this.linksTab = new OO.ui.TabPanelLayout( 'links', {
		label: mw.msg( 'issuetrackerlinks-config-tab-label-links' ),
		expanded: false
	} );
	this.linksGrid = new IssueLinksGrid();
	this.linksTab.$element.append( this.linksGrid.$element );

	this.providersTab = new OO.ui.TabPanelLayout( 'providers', {
		label: mw.msg( 'issuetrackerlinks-config-tab-label-data-providers' ),
		expanded: false
	} );
	this.providersGrid = new DataProvidersGrid();
	this.providersTab.$element.append( this.providersGrid.$element );

	this.addTabPanels( [ this.linksTab, this.providersTab ] );
	this.connect( this, {
		set: ( page ) => {
			location.hash = page.getName();
		}
	} );
};

module.exports = ConfigPanel;
