/* eslint-disable no-shadow */
/* eslint-disable react/prop-types */
/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */
import { createContext, h } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';
import { createPortal } from 'preact/compat';

// Global styles
import '../style/style.scss';


import style from './style.scss';
import Overlay from './overlay';
import { SearchContext } from '../context';
import { GeneralInformation } from  './information-pane';
import { Queries } from './query';

import pluralize from 'pluralize';


const AdminBarButton = props => {
	const { queries } = useContext(SearchContext);
	return (<button {...props}>Search: { pluralize('query', queries.length, true ) }</button>)
}

/**
 * Main app component
 */
const App = props => {
	const [ visible, setVisible ] = useState( false );
	const closeOverlay = e => setVisible( false );
	const openOverlay = e => setVisible( true );
	const toggleOverlay = () => setVisible( ! visible );

	return ( <SearchContext.Provider value={window?.VIPSearchDevTools || { status: 'disabled', queries: [], information: [] }}>
		<div className="search-dev-tools__wrapper">
			<AdminBarButton className={style.ab_btn} onClick={ toggleOverlay } />

			{createPortal((<Overlay isVisible={visible} closeOverlay={closeOverlay} opacity="100">
				<div className={style.vip_search_dev_tools}>
					<h4 class="vip-h4 main_caption">VIP Search Dev Tools</h4>
					<GeneralInformation />
					<Queries />
				</div>
			</Overlay>), document.getElementById('search-dev-tools-portal') ) }
		</div>
	</SearchContext.Provider>
	);
};
export default App;
