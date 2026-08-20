// ErrorBoundary.jsx
import React from "react";

class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error) {
        // Perbarui state agar render berikutnya akan menampilkan fallback UI.
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        // Kamu juga bisa log error ke server
        console.error("Error caught by Error Boundary: ", error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            // Kamu bisa render UI fallback
            return <h1>Something went wrong.</h1>;
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
