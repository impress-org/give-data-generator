import { createRoot } from "@wordpress/element";
import DataGeneratorApp from "./components/DataGeneratorApp";
import "../scss/admin.scss";

document.addEventListener("DOMContentLoaded", (): void => {
  const container: HTMLElement | null = document.getElementById("data-generator-react-root");
  if (container) {
    createRoot(container).render(<DataGeneratorApp />);
  }
});
