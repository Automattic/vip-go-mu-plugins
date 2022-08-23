interface ParselyRecommendationsTitleProps {
	title: string;
}

const ParselyRecommendationsTitle = ( { title }: ParselyRecommendationsTitleProps ) => (
	title ? <p className="parsely-recommendations-list-title">{ title }</p> : <></>
);

export default ParselyRecommendationsTitle;
