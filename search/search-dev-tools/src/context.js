import { createContext } from 'preact';

export const SearchContext = createContext( null );

// Announces a message through the panel's polite live region (screen readers).
export const AnnounceContext = createContext( () => {} );
