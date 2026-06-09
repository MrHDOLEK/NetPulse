export const theme = () => ({
  dark: document.documentElement.dataset.theme === "dark",
  
  get light() {
    return !this.dark;
  },
  toggle() {
    this.dark = !this.dark;
    const v = this.dark ? "dark" : "light";
    document.documentElement.dataset.theme = v;
    localStorage.setItem("netpulse:dark", v);
  },
});
