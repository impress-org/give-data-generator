declare module '*.svg';
declare module '*.module.css';
declare module '*.module.scss';

declare var wp: any;

// WordPress core-data types
declare module '@wordpress/core-data' {
    export const useEntityRecords: (
        kind: string,
        name: string,
        query?: Record<string, any>
    ) => {
        hasResolved: boolean;
        records: any[] | null;
        isResolving: boolean;
    };
}

export {};
