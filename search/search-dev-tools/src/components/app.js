import pluralize from 'pluralize';
import { h } from 'preact';
import { createPortal } from 'preact/compat';
import { useCallback, useContext, useState } from 'preact/hooks';

// Global styles
import '../style/style.scss';

import { GeneralInformation } from './information-pane';
import Overlay from './overlay';
import { Queries } from './query';
import * as style from './style.scss';
import { SearchContext } from '../context';

const AdminBarButton = props => {
	const { queries } = useContext( SearchContext );
	return ( <button { ...props }>Search: { pluralize( 'query', queries.length, true ) }</button> );
};

/**
 * The Main app component.
 * It mounts onto an existing DOM node in the Admin Bar and then renders into a Portal
 * to avoid any interference of Admin Bar CSS.
 *
 * @return {import('preact').VNode} Top-level app component
 */
const App = () => {
	const [ visible, setVisible ] = useState( false );
	const closeOverlay = useCallback( () => setVisible( false ), [] );
	const toggleOverlay = useCallback( () => setVisible( ! visible ), [ visible ] );

	return ( <SearchContext.Provider value={ window?.VIPSearchDevTools || { status: 'disabled', queries: [], information: [] } }>
		<div className="search-dev-tools__wrapper">
			<AdminBarButton class={ style.ab_btn } onClick={ toggleOverlay } />
			{ createPortal(
				( <Overlay isVisible={ visible } closeOverlay={ closeOverlay } opacity="100">
					<div className={ style.vip_search_dev_tools }>
						<h4 className="vip-h4 main_caption">Enterprise Search Dev Tools</h4>
						<GeneralInformation />
						<Queries />
					</div>
				</Overlay> ),
				document.getElementById( 'search-dev-tools-portal' ), // eslint-disable-line no-undef
			) }
		</div>
	</SearchContext.Provider>
	);
};
export default App;
