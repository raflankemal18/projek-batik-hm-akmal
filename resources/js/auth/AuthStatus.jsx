// ./auth/AuthStatus.jsx
import { usePage } from "@inertiajs/react";
import React from "react";

const AuthStatus = ({ children }) => {
    const { auth } = usePage().props;
    console.log(auth);

    return <div>{children}</div>;
};

export default AuthStatus;
