import React from "react";

const InputGroup = ({ label, id, testId, type = "text", value, onChange, icon, placeholder }) => (
    <div>
        <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
        <div className="relative group">
            <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-batik-gold transition-colors">
                {icon}
            </div>
            <input
                type={type}
                id={id}
                data-testid={testId}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                className="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 bg-white focus:border-batik-gold focus:ring-1 focus:ring-batik-gold transition-all shadow-sm outline-none"
            />
        </div>
    </div>
);

export default InputGroup;
