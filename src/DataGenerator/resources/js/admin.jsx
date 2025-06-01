import { render } from "@wordpress/element";
import DataGeneratorApp from "./components/DataGeneratorApp";
import "../scss/admin.scss";
document.addEventListener("DOMContentLoaded", () => { const container = document.getElementById("data-generator-react-root"); if (container) { render(<DataGeneratorApp />, container); } });
