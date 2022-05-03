const ParselyRecommendationsTitle = ( { title } ) => (
	title ? <p className="parsely-recommendations-list-title">{ title }</p> : <></>
);

export default ParselyRecommendationsTitle;
