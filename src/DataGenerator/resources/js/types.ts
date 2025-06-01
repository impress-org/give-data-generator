// Campaign and form data types
export interface Campaign {
    id: string;
    title: string;
    status: string;
}

export interface Donor {
    id: string;
    name: string;
    email: string;
}

export interface FormData {
    [key: string]: any;
}

export interface ApiResponse {
    success: boolean;
    data?: {
        message: string;
    };
}

export interface ResultState {
    success: boolean;
    message: string;
}
